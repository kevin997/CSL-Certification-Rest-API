<?php

namespace App\Support\Certificates;

use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Decides what value every declared template field gets.
 *
 * Pure: no HTTP, no Eloquent, no config reads. CertificateGenerationService
 * gathers the sources and posts the result; this class owns the policy, so it
 * can be exercised without a certificate service or a database.
 *
 * Resolution precedence per declared field:
 *   1. an explicit override from certificate_contents.fields_config
 *   2. the alias default for the field name (NOMS -> fullName, ...)
 *   3. a caller key matching the field name exactly or case-insensitively
 *   4. omitted
 *
 * Anything the template does not declare is always dropped: v2 rejects the
 * whole request when it receives an unknown field.
 */
final class TemplateFieldResolver
{
    /**
     * Default source per field name, after fold().
     *
     * Templates name the same person field NOM, NOMS, NAME or FULLNAME; this
     * table is what lets an English-data API fill a French template. A source
     * that is not a first-class value ('matricule', 'session') is looked up in
     * the caller's additional data.
     *
     * @var array<string, string>
     */
    private const ALIASES = [
        'NOMS' => 'fullName',
        'NOM' => 'fullName',
        'NAME' => 'fullName',
        'FULLNAME' => 'fullName',
        'STUDENTNAME' => 'fullName',
        'LEARNERNAME' => 'fullName',
        'SPECIALITE' => 'courseTitle',
        'SPECIALITY' => 'courseTitle',
        'COURSE' => 'courseTitle',
        'COURSETITLE' => 'courseTitle',
        'FORMATION' => 'courseTitle',
        'FILIERE' => 'courseTitle',
        'MATRICULE' => 'matricule',
        'MATRICULATION' => 'matricule',
        'STUDENTNUMBER' => 'matricule',
        'STUDENTID' => 'matricule',
        'DDD' => 'certificateDate',
        'DATEDEDELIVRANCE' => 'certificateDate',
        'ISSUEDATE' => 'certificateDate',
        'CERTIFICATEDATE' => 'certificateDate',
        'DATE' => 'certificateDate',
        'EXPIRE' => 'expiryDate',
        'EXPIRYDATE' => 'expiryDate',
        'DATEDEXPIRATION' => 'expiryDate',
        // "date d'expiration" folds to DATEDEXPIRATION (the d is kept); the
        // English word order folds without it.
        'DATEEXPIRATION' => 'expiryDate',
        'VALIDITE' => 'validity',
        'VALIDITY' => 'validity',
        'DUREE' => 'validity',
        'SESSION' => 'session',
        'SESSIONNAME' => 'session',
        'PROMOTION' => 'session',
        'COHORT' => 'session',
    ];

    /**
     * Sources that hold a date, so are formatted unless told otherwise.
     *
     * @var list<string>
     */
    private const DATE_SOURCES = ['certificateDate', 'expiryDate', 'issuedDate'];

    private const DEFAULT_DATE_FORMAT = 'd/m/Y';

    /**
     * @param  array<string, mixed>  $overrides  PDF field => override definition
     */
    public function __construct(private readonly array $overrides = []) {}

    /**
     * Build from certificate_contents.fields_config, whose documented shape is
     * {"map": {"<PDF field>": <definition>}}.
     */
    public static function fromFieldsConfig(mixed $fieldsConfig): self
    {
        if (is_string($fieldsConfig)) {
            $decoded = json_decode($fieldsConfig, true);
            $fieldsConfig = is_array($decoded) ? $decoded : [];
        }

        $map = is_array($fieldsConfig) && isset($fieldsConfig['map']) && is_array($fieldsConfig['map'])
            ? $fieldsConfig['map']
            : [];

        return new self($map);
    }

    /**
     * A comparison key that ignores case, accents and separators.
     *
     * "date d'expiration", "DATE_EXPIRATION", "Spécialité" and "SPECIALITE"
     * fold to the same key, so a template redesign keeps its mapping.
     */
    public static function fold(string $name): string
    {
        return strtoupper((string) preg_replace('/[^A-Za-z0-9]+/', '', Str::ascii($name)));
    }

    /**
     * The source a field name maps to by default, or null when the app has no
     * opinion. This is what GET /certificate-templates/{id}/fields reports as
     * maps_to.
     */
    public static function mapsTo(string $fieldName): ?string
    {
        return self::ALIASES[self::fold($fieldName)] ?? null;
    }

    /**
     * Resolve a value for every declared data field.
     *
     * @param  list<string>  $fieldNames  The template's own data field names.
     * @param  array<string, mixed>  $values  Sources plus caller data, merged.
     * @return array<string, string> keyed by the template's own spelling
     */
    public function resolve(array $fieldNames, array $values): array
    {
        $resolved = [];

        foreach ($fieldNames as $fieldName) {
            $value = $this->resolveOverride($fieldName, $values)
                ?? $this->resolveSource(self::mapsTo($fieldName), $values)
                ?? $this->resolveValue($this->lookup($values, $fieldName), false);

            // null and false both cast to '' and would blank a field while the
            // service reported success; arrays are refused outright.
            if ($value === null) {
                continue;
            }

            $resolved[$fieldName] = $value;
        }

        return $resolved;
    }

    /**
     * Format a date-like source. Unparseable input is returned verbatim rather
     * than dropped, so a field still gets something printable.
     */
    public static function formatDate(mixed $value, ?string $format = null): ?string
    {
        $format = $format !== null && $format !== '' ? $format : self::DEFAULT_DATE_FORMAT;

        if ($value instanceof \DateTimeInterface) {
            return $value->format($format);
        }

        if (! is_string($value) && ! is_int($value) && ! is_float($value)) {
            return null;
        }

        $string = trim((string) $value);

        if ($string === '') {
            return null;
        }

        try {
            return Carbon::parse($string)->format($format);
        } catch (\Throwable) {
            return $string;
        }
    }

    /**
     * Render certificate_contents expiry_period/expiry_period_unit as the label
     * a certificate prints: "3 ans", "6 mois", "30 jours".
     */
    public static function validityLabel(mixed $period, mixed $unit): ?string
    {
        if (! is_numeric($period) || (int) $period <= 0) {
            return null;
        }

        $labels = [
            'days' => ['jour', 'jours'],
            'months' => ['mois', 'mois'],
            'years' => ['an', 'ans'],
        ];

        $forms = is_string($unit) ? ($labels[strtolower(trim($unit))] ?? null) : null;

        if ($forms === null) {
            return null;
        }

        $period = (int) $period;

        return $period.' '.($period === 1 ? $forms[0] : $forms[1]);
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function resolveOverride(string $fieldName, array $values): ?string
    {
        $folded = self::fold($fieldName);
        $override = null;
        $found = false;

        foreach ($this->overrides as $name => $definition) {
            if ($name === $fieldName || self::fold((string) $name) === $folded) {
                $override = $definition;
                $found = true;

                break;
            }
        }

        if (! $found || $override === null) {
            return null;
        }

        if (is_string($override)) {
            return $this->resolveSource($override, $values);
        }

        if (! is_array($override)) {
            return null;
        }

        if (array_key_exists('value', $override)) {
            // A literal is intentional, so '' is preserved.
            return $this->resolveValue($override['value'], true);
        }

        if (isset($override['additional'])) {
            return $this->resolveValue(
                $this->lookup($values, (string) $override['additional']),
                false
            );
        }

        if (isset($override['source'])) {
            return $this->resolveSource(
                (string) $override['source'],
                $values,
                isset($override['format']) && is_string($override['format']) ? $override['format'] : null
            );
        }

        return null;
    }

    /**
     * Resolve a named source. Unknown names are treated as additional data,
     * which is what a plain-string override means in practice.
     *
     * @param  array<string, mixed>  $values
     */
    private function resolveSource(?string $source, array $values, ?string $format = null): ?string
    {
        if ($source === null || $source === '') {
            return null;
        }

        $value = $this->lookup($values, $source);

        // MATRICULE normally comes from the caller's additional data, but an
        // access code is a valid fallback when no matricule was supplied.
        if ($value === null && $source === 'matricule') {
            $value = $this->lookup($values, 'accessCode');
        }

        if (in_array($source, self::DATE_SOURCES, true)) {
            return self::formatDate($value, $format);
        }

        return $this->resolveValue($value, false);
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function lookup(array $values, string $key): mixed
    {
        if (array_key_exists($key, $values) && $values[$key] !== null) {
            return $values[$key];
        }

        $folded = self::fold($key);

        foreach ($values as $name => $value) {
            if ($value !== null && self::fold((string) $name) === $folded) {
                return $value;
            }
        }

        return null;
    }

    private function resolveValue(mixed $value, bool $allowEmpty): ?string
    {
        if ($value instanceof \Stringable) {
            $value = (string) $value;
        }

        if ($value === null || is_bool($value) || is_array($value) || is_object($value)) {
            return null;
        }

        $string = (string) $value;

        return ! $allowEmpty && trim($string) === '' ? null : $string;
    }
}
