<?php

namespace App\Services;

use App\Events\CertificateIssued;
use App\Models\Activity;
use App\Models\Block;
use App\Models\CertificateContent;
use App\Models\CertificateTemplate;
use App\Models\Enrollment;
use App\Models\IssuedCertificate;
use App\Models\Template;
use App\Models\ThirdPartyService;
use App\Support\Certificates\TemplateFieldContract;
use App\Support\Certificates\TemplateFieldResolver;
use Illuminate\Http\Client\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class CertificateGenerationService
{
    /**
     * Template contracts, keyed by template name, for this request. A failed
     * lookup is cached as null so an outage is not retried per certificate.
     *
     * @var array<string, TemplateFieldContract|null>
     */
    private array $templateContractCache = [];

    /**
     * The third-party service for certificate generation
     */
    protected ?ThirdPartyService $service;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->service = ThirdPartyService::getServiceByType('certificate_generation');

        // Authenticate with the service if we have credentials but no token.
        // credential() rather than the attributes: a decryption failure on any
        // encrypted column must degrade to "not configured", not throw here.
        if ($this->service
            && $this->service->credential('bearer_token') === null
            && $this->service->hasCredentials('username', 'password')) {
            $this->authenticate();
        }
    }

    /**
     * The template name the certificate service knows a stored template by.
     *
     * Uploads are stored as "templates/<name>.pdf", while the service's field
     * and generation endpoints expect the bare filename including ".pdf".
     */
    public function templateNameFor(CertificateTemplate $template): string
    {
        $path = trim((string) ($template->file_path ?: $template->filename));

        return $this->normaliseTemplateName($path);
    }

    /**
     * The template's field contract, shaped for the dashboard: page geometry,
     * the QR field, and every data field with the source it maps to by default.
     *
     * @return array{page: array{width: float|int|null, height: float|int|null, count: int|null}, qr_field: array{name: string, rect: list<mixed>|null, box: list<mixed>|null}|null, fields: list<array{name: string, type: string|null, rect: list<mixed>|null, maps_to: string|null}>}|null
     */
    public function describeTemplate(string $templateName): ?array
    {
        $contract = $this->templateContract($this->normaliseTemplateName($templateName));

        if ($contract === null) {
            return null;
        }

        return [
            'page' => $contract->page,
            'qr_field' => $contract->qrField,
            'fields' => array_map(
                static fn (array $field): array => [
                    'name' => $field['name'],
                    'type' => $field['type'],
                    'rect' => $field['rect'],
                    'maps_to' => TemplateFieldResolver::mapsTo($field['name']),
                ],
                $contract->dataFields(),
            ),
        ];
    }

    /**
     * The full field contract a template declares, fetched once per request.
     *
     * GET /api/v2/templates/{name}/fields is the contract for what a template
     * can be filled with; sending anything else makes v2 reject the whole
     * request, so this is consulted before every generation.
     */
    private function templateContract(string $templateName): ?TemplateFieldContract
    {
        if (array_key_exists($templateName, $this->templateContractCache)) {
            return $this->templateContractCache[$templateName];
        }

        $response = $this->makeAuthenticatedRequest(
            'get',
            'v2/templates/'.rawurlencode($templateName).'/fields'
        );

        if (! $response || ! $response->successful()) {
            Log::warning('Could not read template fields from certificate service', [
                'template' => $templateName,
                'status' => $response?->status(),
            ]);

            return $this->templateContractCache[$templateName] = null;
        }

        $body = $response->json();

        if (! is_array($body)) {
            Log::warning('Certificate service returned an unreadable field contract', [
                'template' => $templateName,
            ]);

            return $this->templateContractCache[$templateName] = null;
        }

        return $this->templateContractCache[$templateName] = TemplateFieldContract::fromArray($body, $templateName);
    }

    /**
     * Issue a certificate through the v2 endpoint.
     *
     * Returns the v1-shaped array the callers already expect
     * (data.certificate_url / data.preview_url) so the transport change stops
     * at this class.
     *
     * @param  array<string, mixed>  $values
     * @param  mixed  $fieldsConfig  certificate_contents.fields_config (JSON cast value)
     * @return array<string, mixed>|null
     */
    private function issueViaV2(string $templateName, array $values, ?string $reference = null, mixed $fieldsConfig = null): ?array
    {
        $contract = $this->templateContract($templateName);

        if ($contract === null) {
            return null;
        }

        $resolver = TemplateFieldResolver::fromFieldsConfig($fieldsConfig);
        $resolvedFields = $resolver->resolve($contract->dataFieldNames(), $values);

        $payload = [
            'template' => $templateName,
            'fields' => $resolvedFields,
        ];

        if ($reference !== null) {
            $payload['reference'] = $reference;
        }

        // Only when the template actually declares a QR field: the service
        // refuses a qr payload (noQrField) for a template that has none, and a
        // template without one gets no code rather than a rejected request.
        $qr = $this->qrPayload($contract, $values['accessCode'] ?? null);
        if ($qr !== null) {
            $payload['qr'] = $qr;
        }

        $response = $this->makeAuthenticatedRequest('post', 'v2/certificates', $payload);

        if (! $response || ! $response->successful()) {
            Log::error('v2 certificate generation failed', [
                'template' => $templateName,
                'status' => $response?->status(),
                'body' => $response?->body(),
                'sent_fields' => array_keys($resolvedFields),
                'declared_fields' => $contract->dataFieldNames(),
            ]);

            return null;
        }

        $body = $response->json();

        return [
            'data' => [
                'certificate_url' => $body['download_url'] ?? null,
                'preview_url' => $body['preview_url'] ?? null,
                'identifier' => $body['identifier'] ?? null,
                'expires_at' => null,
            ],
        ];
    }

    /**
     * The qr payload for a template, or null when there is nothing to stamp.
     *
     * The QR field is excluded from `fields` (the service rejects sending it as
     * text) and is rendered from qr.url instead.
     *
     * @return array{url: string}|null
     */
    private function qrPayload(TemplateFieldContract $contract, mixed $accessCode): ?array
    {
        if (! $contract->hasQrField()) {
            return null;
        }

        $base = config('services.certificate_generation.qr_verify_base_url');

        // The service allow-lists QR hosts, so an unconfigured deployment would
        // have every generation rejected rather than simply going out without
        // a QR. Logged because the certificate will have a blank QR field.
        if (! is_string($base) || trim($base) === '') {
            Log::warning('Template declares a QR field but no QR verification base URL is configured', [
                'template' => $contract->template,
                'qr_field' => $contract->qrFieldName(),
            ]);

            return null;
        }

        if (! is_string($accessCode) || trim($accessCode) === '') {
            return null;
        }

        return ['url' => rtrim($base, '/').'/'.$accessCode];
    }

    private function normaliseTemplateName(string $templateName): string
    {
        return str_starts_with($templateName, 'templates/')
            ? substr($templateName, strlen('templates/'))
            : $templateName;
    }

    /**
     * The service's config JSON, whether the cast returned an array or (for a
     * stale model) a string. json_decode() on the cast array used to throw a
     * TypeError that catch (\Exception) could not catch.
     *
     * @return array<string, mixed>
     */
    private function serviceConfig(): array
    {
        $config = $this->service?->config;

        if (is_string($config)) {
            $decoded = json_decode($config, true);

            return is_array($decoded) ? $decoded : [];
        }

        return is_array($config) ? $config : [];
    }

    /**
     * Everything the app can offer a template, plus the caller's own keys.
     *
     * The template decides which of these are actually sent: the resolver maps
     * declared PDF field names onto these sources. Computed sources win over a
     * caller key of the same name (the caller values are already folded into
     * them), while any other caller key rides along as additional data.
     *
     * @param  array<string, mixed>  $userData
     * @return array<string, mixed>
     */
    private function valuesForCertificate(
        CertificateContent $certificateContent,
        array $userData,
        ?Enrollment $enrollment,
        string $accessCode,
    ): array {
        $sources = [
            'fullName' => $userData['fullName'] ?? 'Student Name',
            'courseTitle' => $userData['courseTitle'] ?? $this->courseTitleFor($certificateContent, $enrollment),
            'certificateTitle' => $userData['certificateTitle'] ?? $certificateContent->title ?? 'Certificate',
            'certificateDate' => $userData['certificateDate'] ?? now()->format('F j, Y'),
            'expiryDate' => $userData['expiryDate'] ?? $this->expiryDateFromContent($certificateContent),
            'validity' => $userData['validity'] ?? TemplateFieldResolver::validityLabel(
                $certificateContent->expiry_period,
                $certificateContent->expiry_period_unit,
            ),
            'issuedDate' => $userData['issuedDate'] ?? $userData['issued_date'] ?? now(),
            'userEmail' => $userData['userEmail'] ?? $userData['email'] ?? null,
            'userId' => $userData['userId'] ?? $userData['user_id'] ?? null,
            'courseId' => $userData['courseId'] ?? $userData['course_id'] ?? null,
            'accessCode' => $accessCode,
        ];

        return array_merge($userData, $sources);
    }

    private function courseTitleFor(CertificateContent $certificateContent, ?Enrollment $enrollment): string
    {
        if ($enrollment && $enrollment->course) {
            return $enrollment->course->title;
        }

        return $this->getTemplateTitleFromCertificateContent($certificateContent) ?: 'Certificate';
    }

    /**
     * The date the certificate content's expiry period ends, or null when it
     * never expires.
     */
    private function expiryDateFromContent(CertificateContent $certificateContent): ?\DateTimeInterface
    {
        $period = $certificateContent->expiry_period;

        if (! is_numeric($period) || (int) $period <= 0) {
            return null;
        }

        return match ($certificateContent->expiry_period_unit) {
            'days' => now()->addDays((int) $period),
            'months' => now()->addMonths((int) $period),
            'years' => now()->addYears((int) $period),
            default => null,
        };
    }

    /**
     * Authenticate with the certificate service and store the bearer token
     *
     * @return bool True if authentication was successful, false otherwise
     */
    public function authenticate(): bool
    {
        if (! $this->service) {
            Log::error('Certificate generation service not configured');

            return false;
        }

        $username = $this->service->credential('username');
        $password = $this->service->credential('password');

        if ($username === null || $password === null) {
            Log::error('Certificate generation service credentials are missing or unreadable');

            return false;
        }

        try {
            $config = $this->serviceConfig();
            $verifySSL = $config['verify_ssl'] ?? false;

            // Disable SSL verification for the request
            $response = Http::withOptions([
                'verify' => $verifySSL,
            ])->post($this->service->base_url.'/api/login', [
                'email' => $username,
                'password' => $password,
            ]);

            $token = $response->json('data.access_token');

            if ($response->successful() && is_string($token) && $token !== '') {
                // Query-builder update instead of $model->save(): saving reads
                // every encrypted sibling (username, password, ...) through
                // its cast, so one unreadable column would throw here and
                // leave the new token unpersisted.
                ThirdPartyService::query()
                    ->whereKey($this->service->getKey())
                    ->update([
                        'bearer_token' => Crypt::encryptString($token),
                        'updated_at' => now(),
                    ]);

                $this->service->setAttribute('bearer_token', $token);

                Log::info('Successfully authenticated with certificate service');

                return true;
            }

            Log::error('Failed to authenticate with certificate service: '.$response->body());

            return false;
        } catch (\Exception $e) {
            Log::error('Error authenticating with certificate service: '.$e->getMessage());

            return false;
        }
    }

    /**
     * Make an authenticated API call to the certificate service with automatic re-authentication on 401
     *
     * @param  string  $method  HTTP method (get, post, put, delete)
     * @param  string  $endpoint  API endpoint (without base URL)
     * @param  array  $data  Request data
     * @param  array  $files  Files to attach to the request
     * @return Response|null Response or null if failed
     */
    protected function makeAuthenticatedRequest(string $method, string $endpoint, array $data = [], array $files = []): ?Response
    {
        if (! $this->service) {
            Log::error('Certificate generation service not configured');

            return null;
        }

        $url = $this->service->base_url.'/api/'.ltrim($endpoint, '/');

        try {
            $config = $this->serviceConfig();
            $verifySSL = $config['verify_ssl'] ?? false;

            // Prepare the request with SSL verification disabled
            $request = Http::withOptions([
                'verify' => $verifySSL,
            ])->withToken($this->service->credential('bearer_token'));

            // Attach files if any
            foreach ($files as $name => $file) {
                if ($file instanceof UploadedFile) {
                    $request->attach($name, file_get_contents($file->getRealPath()), $file->getClientOriginalName());
                } else {
                    $request->attach($name, $file['contents'], $file['name']);
                }
            }

            // Make the request
            $response = $request->$method($url, $data);

            // If unauthorized, try to re-authenticate and retry
            if ($response->status() === 401) {
                Log::info('Token expired, re-authenticating with certificate service');
                if ($this->authenticate()) {
                    // Prepare a new request with the fresh token and the same SSL verification settings
                    $request = Http::withOptions([
                        'verify' => $verifySSL,
                    ])->withToken($this->service->credential('bearer_token'));

                    // Attach files again if any
                    foreach ($files as $name => $file) {
                        if ($file instanceof UploadedFile) {
                            $request->attach($name, file_get_contents($file->getRealPath()), $file->getClientOriginalName());
                        } else {
                            $request->attach($name, $file['contents'], $file['name']);
                        }
                    }

                    // Retry the request
                    $response = $request->$method($url, $data);
                }
            }

            return $response;
        } catch (\Exception $e) {
            Log::error("Error making {$method} request to {$endpoint}: ".$e->getMessage());

            return null;
        }
    }

    /**
     * Upload a certificate template to the certificate service
     *
     * @param  UploadedFile  $file  The template PDF file
     * @param  string  $name  The name of the template
     * @return array|null The response from the certificate service or null if failed
     */
    public function uploadTemplate(UploadedFile $file, string $name): ?array
    {
        if (! $this->service) {
            Log::error('Certificate generation service not configured');

            return null;
        }

        try {
            $response = $this->makeAuthenticatedRequest(
                'post',
                'templates/upload',
                ['name' => $name],
                ['template' => $file]
            );

            // makeAuthenticatedRequest returns null when the certificate
            // service is unreachable. Reading ->status() on that null threw an
            // Error, which catch (\Exception) below does not catch, turning a
            // handled outage into an uncaught 500.
            if ($response === null) {
                Log::error('Certificate service unreachable while uploading template');

                return null;
            }

            if ($response->successful()) {
                return $response->json();
            }

            Log::error('Failed to upload template to certificate service', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('Exception when uploading template to certificate service', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return null;
        }
    }

    /**
     * List all templates from the certificate service
     *
     * @return array|null The list of templates or null if failed
     */
    public function listTemplates(): ?array
    {
        if (! $this->service) {
            Log::error('Certificate generation service not configured');

            return null;
        }

        try {
            $response = Http::withToken($this->service->credential('bearer_token'))
                ->get($this->service->base_url.'/api/templates');

            if ($response->successful()) {
                return $response->json();
            }

            Log::error('Failed to list templates from certificate service', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('Exception when listing templates from certificate service', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return null;
        }
    }

    /**
     * Delete a template from the certificate service
     *
     * @param  string  $filename  The filename of the template to delete
     * @return bool Whether the deletion was successful
     */
    public function deleteTemplate(string $filename): bool
    {
        if (! $this->service) {
            Log::error('Certificate generation service not configured');

            return false;
        }

        try {
            $response = $this->makeAuthenticatedRequest(
                'delete',
                'templates/'.$filename
            );

            if ($response && $response->successful()) {
                return true;
            }

            Log::error('Failed to delete template from certificate service', [
                'status' => $response ? $response->status() : 'No response',
                'body' => $response ? $response->body() : 'No response',
            ]);

            return false;
        } catch (\Exception $e) {
            Log::error('Exception when deleting template from certificate service', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return false;
        }
    }

    /**
     * Generate a certificate using the certificate service
     *
     * @param  CertificateContent  $certificateContent  The certificate content
     * @param  array  $userData  The user data for the certificate (name, etc.)
     * @param  string|null  $templateName  The name of the template to use (defaults to the one in certificate content)
     * @param  Enrollment  $enrollment  The enrollment record for the user
     * @return array|null The generated certificate data or null if failed
     */
    public function generateCertificate(CertificateContent $certificateContent, array $userData, ?string $templateName = null, ?Enrollment $enrollment = null): ?array
    {
        if (! $this->service) {
            Log::error('Certificate generation service not configured');

            return null;
        }

        try {
            // Get the template to use
            $template = null;
            if ($certificateContent->certificate_template_id) {
                $template = CertificateTemplate::find($certificateContent->certificate_template_id);
            }

            // Generate a unique access code for this certificate
            $accessCode = $this->generateAccessCode();

            // Determine which template to use
            $templateToUse = $this->normaliseTemplateName(
                $templateName ?: ($template ? $this->templateNameFor($template) : 'default')
            );

            Log::info('Enrollment course title: '.($enrollment && $enrollment->course ? $enrollment->course->title : 'No course title'));
            Log::info('Certificate content course title: '.$this->getTemplateTitleFromCertificateContent($certificateContent));

            // Everything the app can offer. What actually gets sent is decided
            // by the template, not by this list -- additionalData the caller
            // merged into $userData rides along, so a tenant template can
            // declare fields this code has never heard of.
            $values = $this->valuesForCertificate($certificateContent, $userData, $enrollment, $accessCode);

            $result = $this->issueViaV2($templateToUse, $values, $accessCode, $certificateContent->fields_config);

            if ($result !== null) {

                // Check if the response contains the certificate URLs
                if (isset($result['data']['certificate_url'])) {

                    // Add the access code and certificate data to the result for the client
                    $result['accessCode'] = $accessCode;

                    if (! $enrollment) {
                        // Update the certificate content metadata (original implementation)
                        $metadata = $certificateContent->metadata ?? [];
                        $metadata['certificate_url'] = $result['data']['certificate_url'];
                        $metadata['preview_url'] = $result['data']['preview_url'] ?? null;
                        $metadata['access_code'] = $accessCode;
                        $metadata['generated_at'] = now()->toIso8601String();

                        // Save the updated metadata to the certificate content
                        $certificateContent->metadata = $metadata;
                        $certificateContent->save();
                    } else {

                        // Also create the IssuedCertificate record (new implementation)
                        $issuedCertificate = $this->createIssuedCertificate(
                            $certificateContent,
                            $userData,
                            $accessCode,
                            $result['data']['certificate_url'],
                            $result['data']['preview_url'] ?? null,
                        );

                        // Add the issued certificate ID if it was created successfully
                        if ($issuedCertificate) {
                            $result['issuedCertificateId'] = $issuedCertificate->id;
                        }
                    }

                    return $result;
                }

                Log::error('Certificate URL not found in response', ['response' => $result]);

                return null;
            }

            // issueViaV2 has already logged the status, body and the field
            // mismatch, which is the failure worth seeing here.
            Log::error('Failed to generate certificate', ['template' => $templateToUse]);

            return null;
        } catch (\Exception $e) {
            Log::error('Exception when generating certificate', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return null;
        }
    }

    /**
     * Verify a certificate using its access code
     *
     * @param  string  $accessCode  The access code of the certificate
     * @return array|null The verification result or null if failed
     */
    public function verifyCertificate(string $accessCode): ?array
    {
        if (! $this->service) {
            Log::error('Certificate generation service not configured');

            return null;
        }

        try {
            $response = Http::withToken($this->service->credential('bearer_token'))
                ->post($this->service->base_url.'/api/certificates/verify', [
                    'accessCode' => $accessCode,
                ]);

            if ($response->successful()) {
                return $response->json();
            }

            Log::error('Failed to verify certificate', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('Exception when verifying certificate', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return null;
        }
    }

    /**
     * Regenerate a certificate with a specific access code
     * This is used when the certificate URLs are no longer valid but the certificate record exists
     *
     * @param  CertificateContent  $certificateContent  The certificate content
     * @param  array  $userData  The user data for the certificate
     * @param  string  $accessCode  The existing access code to reuse
     * @param  string|null  $templateName  The name of the template to use
     * @param  Enrollment|null  $enrollment  The enrollment record for the user
     * @return array|null The regenerated certificate data or null if failed
     */
    public function regenerateCertificate(
        CertificateContent $certificateContent,
        array $userData,
        string $accessCode,
        ?string $templateName = null,
        ?Enrollment $enrollment = null
    ): ?array {
        if (! $this->service) {
            Log::error('Certificate generation service not configured');

            return null;
        }

        try {
            // Determine which template to use
            $template = $certificateContent->certificate_template_id
                ? CertificateTemplate::find($certificateContent->certificate_template_id)
                : null;

            $templateToUse = $this->normaliseTemplateName(
                $templateName ?: ($template ? $this->templateNameFor($template) : 'default')
            );

            Log::info('Regenerating certificate with access code: '.$accessCode);

            // Same values as generateCertificate, reusing the existing access
            // code; the template decides which of them are actually sent.
            $values = $this->valuesForCertificate($certificateContent, $userData, $enrollment, $accessCode);

            $result = $this->issueViaV2($templateToUse, $values, $accessCode, $certificateContent->fields_config);

            if ($result !== null) {
                // Check if the response contains the certificate URLs
                if (isset($result['data']['certificate_url'])) {
                    // Add the access code to the result
                    $result['accessCode'] = $accessCode;

                    Log::info('Certificate regenerated successfully with URLs: '.json_encode($result['data']));

                    return $result;
                }

                Log::error('Certificate URL not found in regeneration response', ['response' => $result]);

                return null;
            }

            // issueViaV2 logs the status, body and field mismatch.
            Log::error('Failed to regenerate certificate', ['template' => $templateToUse]);

            return null;
        } catch (\Exception $e) {
            Log::error('Exception when regenerating certificate', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return null;
        }
    }

    /**
     * Get the download URL for a certificate
     *
     * @param  CertificateContent  $certificateContent  The certificate content
     * @return string|null The download URL or null if failed
     */
    public function getCertificateDownloadUrl(CertificateContent $certificateContent): ?string
    {
        if (! $this->service) {
            Log::error('Certificate generation service not configured');

            return null;
        }

        // Check if we have metadata with certificate_url
        $metadata = $certificateContent->metadata;

        // Simply return the stored certificate_url if available
        if (isset($metadata['certificate_url'])) {
            return $metadata['certificate_url'];
        }

        // Fallback to using access code if certificate_url is not available
        $accessCode = $metadata['access_code'] ?? null;
        if ($accessCode) {
            return $this->service->base_url.'/api/certificates/download/'.$accessCode;
        }

        return null;
    }

    /**
     * Get the preview URL for a certificate
     *
     * @param  CertificateContent  $certificateContent  The certificate content
     * @return string|null The preview URL or null if failed
     */
    public function getCertificatePreviewUrl(CertificateContent $certificateContent): ?string
    {
        if (! $this->service) {
            Log::error('Certificate generation service not configured');

            return null;
        }

        // Check if we have metadata with preview_url
        $metadata = $certificateContent->metadata;

        // First check if we have a stored preview URL
        if (isset($metadata['preview_url'])) {
            return $metadata['preview_url'];
        }

        // Fallback: if we have certificate_url but no preview_url
        if (isset($metadata['certificate_url'])) {
            // Extract the file path from the certificate_url
            $url = $metadata['certificate_url'];
            $path = basename($url);

            // Return the preview URL (no need for signing)
            return $this->service->base_url.'/api/certificates/preview/'.$path;
        }

        // Fallback to using access code if certificate_url is not available
        $accessCode = $metadata['access_code'] ?? null;
        if ($accessCode) {
            return $this->service->base_url.'/api/certificates/preview/'.$accessCode;
        }

        return null;
    }

    /**
     * Get a URL for the certificate service with the given path
     *
     * @param  string  $path  The path to append to the base URL
     * @return string|null The full URL or null if the service is not configured
     */
    public function getServiceUrl(string $path): ?string
    {
        if (! $this->service) {
            Log::error('Certificate generation service not configured');

            return null;
        }

        return $this->service->base_url.$path;
    }

    /**
     * Generate a unique access code for certificates
     *
     * @return string The generated access code
     */
    protected function generateAccessCode(): string
    {
        return strtoupper(Str::random(8));
    }

    /**
     * Create an IssuedCertificate record for a generated certificate
     *
     * @param  CertificateContent  $certificateContent  The certificate content
     * @param  array  $userData  The user data for the certificate
     * @param  string  $accessCode  The unique access code for the certificate
     * @param  string  $certificateUrl  The URL to the generated certificate
     * @param  string|null  $previewUrl  The URL to the certificate preview (optional)
     * @return IssuedCertificate|null The created IssuedCertificate or null if failed
     */
    protected function createIssuedCertificate(
        CertificateContent $certificateContent,
        array $userData,
        string $accessCode,
        string $certificateUrl,
        ?string $previewUrl = null,
    ): ?IssuedCertificate {
        try {
            // Import the IssuedCertificate model
            $issuedCertificateClass = '\App\Models\IssuedCertificate';

            // Create the IssuedCertificate record
            $issuedCertificate = new $issuedCertificateClass;
            $issuedCertificate->user_id = $userData['user_id'] ?? null;
            $issuedCertificate->course_id = $certificateContent->course_id;
            $issuedCertificate->certificate_content_id = $certificateContent->id;
            $issuedCertificate->certificate_number = $accessCode;
            $issuedCertificate->status = 'issued';
            $issuedCertificate->file_path = $certificateUrl; // Set file_path to the certificate URL

            // Set issued date from userData if provided, otherwise use current time
            $issuedCertificate->issued_date = isset($userData['issued_date']) ?
                new \DateTime($userData['issued_date']) : now();

            // Set expiry date if provided in userData
            if (isset($userData['expiry_date'])) {
                $issuedCertificate->expiry_date = new \DateTime($userData['expiry_date']);
                // Calculate expiry date based on certificate content settings
                $expiryDate = clone $issuedCertificate->issued_date;
                switch ($certificateContent->expiry_period_unit) {
                    case 'days':
                        $expiryDate->modify("+{$certificateContent->expiry_period} days");
                        break;
                    case 'months':
                        $expiryDate->modify("+{$certificateContent->expiry_period} months");
                        break;
                    case 'years':
                        $expiryDate->modify("+{$certificateContent->expiry_period} years");
                        break;
                }
                $issuedCertificate->expiry_date = $expiryDate;
            }

            // Store metadata about the certificate in custom_fields (JSON column)
            $issuedCertificate->custom_fields = [
                'certificate_url' => $certificateUrl,
                'preview_url' => $previewUrl,
                'recipient_name' => $userData['fullName'] ?? 'Student Name',
                'course_title' => $certificateContent->title ?? 'Course Title',
                'certificate_date' => $userData['certificateDate'] ?? now()->format('F j, Y'),
            ];

            $issuedCertificate->save();

            // Trigger the event after successful creation
            if ($issuedCertificate && $issuedCertificate->id) {
                event(new CertificateIssued($issuedCertificate));
            }

            return $issuedCertificate;
        } catch (\Exception $e) {
            Log::error('Failed to create IssuedCertificate record', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return null;
        }
    }

    /**
     * Get the template title from certificate content by traversing the relationship chain
     * CertificateContent -> Activity -> Block -> Template
     */
    protected function getTemplateTitleFromCertificateContent(CertificateContent $certificateContent): ?string
    {
        try {
            // Get the activity by ID instead of using the morphOne relationship
            if (! $certificateContent->activity_id) {
                Log::info('No activity_id found in certificate content');

                return null;
            }

            $activity = Activity::find($certificateContent->activity_id);

            if (! $activity) {
                Log::info('Activity not found with ID: '.$certificateContent->activity_id);

                return null;
            }

            Log::info('Activity found: '.($activity->title ?? 'No title'));

            // Get the block associated with the activity
            if (! $activity->block_id) {
                Log::info('No block_id found in activity');

                return null;
            }

            $block = Block::find($activity->block_id);

            if (! $block) {
                Log::info('Block not found with ID: '.$activity->block_id);

                return null;
            }

            // Get the template associated with the block
            if (! $block->template_id) {
                Log::info('No template_id found in block');

                return null;
            }

            $template = Template::find($block->template_id);

            if (! $template) {
                Log::info('Template not found with ID: '.$block->template_id);

                return null;
            }

            Log::info('Template title found: '.($template->title ?? 'No title'));

            return $template->title;
        } catch (\Exception $e) {
            Log::error('Failed to get template title from certificate content', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return null;
        }
    }
}
