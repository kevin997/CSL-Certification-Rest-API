<?php

namespace Tests\Unit\Certificates;

use App\Support\Certificates\TemplateFieldResolver;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

/**
 * The mapping policy, exercised without HTTP, Eloquent or config.
 *
 * This is where the "which app value fills which PDF field" rules live, so
 * every rule the certificate service depends on is testable in isolation from
 * a template or a running service.
 */
class TemplateFieldResolverTest extends TestCase
{
    public function test_fold_ignores_case_accents_and_separators(): void
    {
        $this->assertSame('DATEDEXPIRATION', TemplateFieldResolver::fold("date d'expiration"));
        $this->assertSame('DATEEXPIRATION', TemplateFieldResolver::fold('DATE_EXPIRATION'));
        $this->assertSame('SPECIALITE', TemplateFieldResolver::fold('Spécialité'));
        $this->assertSame('SPECIALITE', TemplateFieldResolver::fold(' specialite '));
    }

    public function test_maps_to_reports_the_default_source_for_a_field_name(): void
    {
        $this->assertSame('fullName', TemplateFieldResolver::mapsTo('NOMS'));
        $this->assertSame('fullName', TemplateFieldResolver::mapsTo('Nom'));
        $this->assertSame('fullName', TemplateFieldResolver::mapsTo('student_name'));
        $this->assertSame('courseTitle', TemplateFieldResolver::mapsTo('SPECIALITE'));
        $this->assertSame('courseTitle', TemplateFieldResolver::mapsTo('Filière'));
        $this->assertSame('matricule', TemplateFieldResolver::mapsTo('MATRICULE'));
        $this->assertSame('certificateDate', TemplateFieldResolver::mapsTo('DDD'));
        $this->assertSame('expiryDate', TemplateFieldResolver::mapsTo("date d'expiration"));
        $this->assertSame('validity', TemplateFieldResolver::mapsTo('VALIDITE'));
        $this->assertSame('session', TemplateFieldResolver::mapsTo('SESSION'));
        $this->assertNull(TemplateFieldResolver::mapsTo('customField'));
    }

    public function test_aliases_fill_a_french_template_from_canonical_sources(): void
    {
        $resolver = new TemplateFieldResolver;

        $fields = $resolver->resolve(
            ['NOMS', 'SPECIALITE', 'MATRICULE', 'DDD', 'EXPIRE', 'VALIDITE', 'SESSION'],
            [
                'fullName' => 'RALPH DARYL',
                'courseTitle' => 'MARKETING DIGITAL',
                'accessCode' => '05CBRANDSS0126',
                'certificateDate' => '2026-04-07',
                'expiryDate' => '2027-04-07',
                'validity' => '1 an',
                'session' => 'Session 7 Avril',
            ],
        );

        $this->assertSame([
            'NOMS' => 'RALPH DARYL',
            'SPECIALITE' => 'MARKETING DIGITAL',
            'MATRICULE' => '05CBRANDSS0126',
            'DDD' => '07/04/2026',
            'EXPIRE' => '07/04/2027',
            'VALIDITE' => '1 an',
            'SESSION' => 'Session 7 Avril',
        ], $fields);
    }

    public function test_a_field_name_with_no_alias_uses_the_callers_own_key(): void
    {
        $resolver = new TemplateFieldResolver;

        // Keyed by the template's own spelling so pdftk finds the field.
        $this->assertSame(
            ['CustomField' => 'anything'],
            $resolver->resolve(['CustomField'], ['customfield' => 'anything'])
        );
    }

    public function test_an_alias_that_has_no_source_falls_back_to_the_field_name_key(): void
    {
        /* fullName was not supplied, but the caller named the PDF field. */
        $resolver = new TemplateFieldResolver;

        $this->assertSame(
            ['NOMS' => 'RALPH'],
            $resolver->resolve(['NOMS'], ['NOMS' => 'RALPH'])
        );
    }

    public function test_matricule_prefers_additional_data_then_the_access_code(): void
    {
        $resolver = new TemplateFieldResolver;

        $this->assertSame(
            ['MATRICULE' => '05CBRANDSS0126'],
            $resolver->resolve(['MATRICULE'], ['matricule' => '05CBRANDSS0126', 'accessCode' => 'CODE1'])
        );

        $this->assertSame(
            ['MATRICULE' => 'CODE1'],
            $resolver->resolve(['MATRICULE'], ['accessCode' => 'CODE1'])
        );
    }

    public function test_content_overrides_beat_aliases(): void
    {
        $resolver = new TemplateFieldResolver([
            'NOMS' => ['value' => 'LITERAL NAME'],
            'SPECIALITE' => ['source' => 'courseTitle'],
            'SESSION' => ['additional' => 'cohort'],
        ]);

        $this->assertSame([
            'NOMS' => 'LITERAL NAME',
            'SPECIALITE' => 'MARKETING',
            'SESSION' => 'Cohort 7',
        ], $resolver->resolve(
            ['NOMS', 'SPECIALITE', 'SESSION'],
            [
                'fullName' => 'RALPH',
                'courseTitle' => 'MARKETING',
                'cohort' => 'Cohort 7',
            ],
        ));
    }

    public function test_override_field_matching_folds_case_and_separators(): void
    {
        $resolver = new TemplateFieldResolver(['date expiration' => ['source' => 'certificateDate']]);

        $this->assertSame(
            ['DATE_EXPIRATION' => '07/04/2026'],
            $resolver->resolve(['DATE_EXPIRATION'], ['certificateDate' => '2026-04-07'])
        );
    }

    public function test_a_literal_empty_override_is_kept_but_an_empty_source_is_skipped(): void
    {
        $resolver = new TemplateFieldResolver(['NOMS' => ['value' => '']]);

        $this->assertSame(['NOMS' => ''], $resolver->resolve(['NOMS', 'SPECIALITE'], ['fullName' => 'RALPH']));
    }

    public function test_an_override_pointing_at_missing_data_falls_through_to_the_alias(): void
    {
        $resolver = new TemplateFieldResolver(['NOMS' => ['source' => 'userEmail']]);

        $this->assertSame(
            ['NOMS' => 'RALPH'],
            $resolver->resolve(['NOMS'], ['fullName' => 'RALPH'])
        );
    }

    public function test_dates_default_to_d_m_y_and_honour_an_explicit_format(): void
    {
        $this->assertSame('07/04/2026', TemplateFieldResolver::formatDate('2026-04-07'));
        $this->assertSame('2026-04-07', TemplateFieldResolver::formatDate('2026-04-07', 'Y-m-d'));
        $this->assertSame('07/04/2026', TemplateFieldResolver::formatDate(new DateTimeImmutable('2026-04-07')));
        $this->assertSame('April 7, 2026', TemplateFieldResolver::formatDate('April 7, 2026', 'F j, Y'));
    }

    public function test_an_unparseable_date_is_shown_verbatim_rather_than_blank(): void
    {
        $this->assertSame('not a date', TemplateFieldResolver::formatDate('not a date'));
        $this->assertNull(TemplateFieldResolver::formatDate('  '));
        $this->assertNull(TemplateFieldResolver::formatDate(null));
    }

    public function test_validity_is_rendered_from_the_expiry_period_and_unit(): void
    {
        $this->assertSame('3 ans', TemplateFieldResolver::validityLabel(3, 'years'));
        $this->assertSame('1 an', TemplateFieldResolver::validityLabel(1, 'years'));
        $this->assertSame('6 mois', TemplateFieldResolver::validityLabel(6, 'months'));
        $this->assertSame('30 jours', TemplateFieldResolver::validityLabel(30, 'days'));
        $this->assertSame('1 jour', TemplateFieldResolver::validityLabel(1, 'days'));
        $this->assertNull(TemplateFieldResolver::validityLabel(null, 'years'));
        $this->assertNull(TemplateFieldResolver::validityLabel(0, 'years'));
        $this->assertNull(TemplateFieldResolver::validityLabel(3, 'weeks'));
    }

    public function test_unfillable_values_are_omitted(): void
    {
        $resolver = new TemplateFieldResolver;

        $this->assertSame([], $resolver->resolve(
            ['NOMS', 'SPECIALITE', 'SESSION', 'EXPIRE'],
            [
                'fullName' => null,
                'courseTitle' => false,
                'session' => ['not', 'scalar'],
                'expiryDate' => '',
            ],
        ));
    }

    public function test_only_declared_fields_are_returned(): void
    {
        $resolver = new TemplateFieldResolver;

        $this->assertSame(
            ['NOMS' => 'RALPH'],
            $resolver->resolve(['NOMS'], ['fullName' => 'RALPH', 'courseTitle' => 'MARKETING'])
        );
    }

    public function test_fields_config_json_is_accepted_and_its_map_is_used(): void
    {
        $resolver = TemplateFieldResolver::fromFieldsConfig(json_encode(['map' => ['NOMS' => ['additional' => 'noms']]]));

        $this->assertSame(
            ['NOMS' => 'RALPH'],
            $resolver->resolve(['NOMS'], ['noms' => 'RALPH'])
        );
    }

    public function test_a_fields_config_without_a_map_is_ignored(): void
    {
        $resolver = TemplateFieldResolver::fromFieldsConfig(['something' => 'else']);

        $this->assertSame(
            ['NOMS' => 'RALPH'],
            $resolver->resolve(['NOMS'], ['fullName' => 'RALPH'])
        );
    }
}
