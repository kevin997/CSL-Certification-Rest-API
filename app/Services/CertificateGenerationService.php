<?php

namespace App\Services;

use App\Models\CertificateContent;
use App\Models\CertificateTemplate;
use App\Models\ThirdPartyService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CertificateGenerationService
{
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
    }

    /**
     * Upload a certificate template to the certificate service
     *
     * @param UploadedFile $file The template PDF file
     * @param string $name The name of the template
     * @return array|null The response from the certificate service or null if failed
     */
    public function uploadTemplate(UploadedFile $file, string $name): ?array
    {
        if (!$this->service) {
            Log::error('Certificate generation service not configured');
            return null;
        }

        try {
            $response = Http::withToken($this->service->bearer_token)
                ->attach('template', file_get_contents($file->getRealPath()), $file->getClientOriginalName())
                ->post($this->service->base_url . '/api/templates/upload', [
                    'name' => $name
                ]);

            if ($response->successful()) {
                return $response->json();
            }

            Log::error('Failed to upload template to certificate service', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);
            return null;
        } catch (\Exception $e) {
            Log::error('Exception when uploading template to certificate service', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
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
        if (!$this->service) {
            Log::error('Certificate generation service not configured');
            return null;
        }

        try {
            $response = Http::withToken($this->service->bearer_token)
                ->get($this->service->base_url . '/api/templates');

            if ($response->successful()) {
                return $response->json();
            }

            Log::error('Failed to list templates from certificate service', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);
            return null;
        } catch (\Exception $e) {
            Log::error('Exception when listing templates from certificate service', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }

    /**
     * Delete a template from the certificate service
     *
     * @param string $filename The filename of the template to delete
     * @return bool Whether the deletion was successful
     */
    public function deleteTemplate(string $filename): bool
    {
        if (!$this->service) {
            Log::error('Certificate generation service not configured');
            return false;
        }

        try {
            $response = Http::withToken($this->service->bearer_token)
                ->delete($this->service->base_url . '/api/templates/' . $filename);

            if ($response->successful()) {
                return true;
            }

            Log::error('Failed to delete template from certificate service', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);
            return false;
        } catch (\Exception $e) {
            Log::error('Exception when deleting template from certificate service', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * Generate a certificate using the certificate service
     *
     * @param CertificateContent $certificateContent The certificate content
     * @param array $userData The user data for the certificate (name, etc.)
     * @param string|null $templateName The name of the template to use (defaults to the one in certificate content)
     * @return array|null The generated certificate data or null if failed
     */
    public function generateCertificate(CertificateContent $certificateContent, array $userData, ?string $templateName = null): ?array
    {
        if (!$this->service) {
            Log::error('Certificate generation service not configured');
            return null;
        }

        try {
            // Get the template to use
            $template = null;
            if ($certificateContent->certificate_template_id) {
                $template = CertificateTemplate::find($certificateContent->certificate_template_id);
            }

            // Use the provided template name, or the one from the template, or a default
            $templateToUse = $templateName ?? ($template ? $template->filename : 'default.pdf');

            // Generate a unique access code for verification
            $accessCode = Str::random(12);

            // Prepare the data for the certificate
            $certificateData = [
                'template_name' => $templateToUse,
                'data' => [
                    'fullName' => $userData['fullName'] ?? 'Student Name',
                    'courseTitle' => $certificateContent->title ?? 'Course Title',
                    'certificateDate' => $userData['certificateDate'] ?? now()->format('F j, Y'),
                    'accessCode' => $accessCode,
                    // Add any custom fields from the certificate content
                    'signatoryName' => $certificateContent->signatory_name ?? '',
                    'signatoryTitle' => $certificateContent->signatory_title ?? '',
                ]
            ];

            // Add any custom fields from the certificate content
            if (!empty($certificateContent->custom_fields) && is_array($certificateContent->custom_fields)) {
                foreach ($certificateContent->custom_fields as $field) {
                    $certificateData['data'][$field['name']] = $field['value'];
                }
            }

            $response = Http::withToken($this->service->bearer_token)
                ->post($this->service->base_url . '/api/certificates/generate', $certificateData);

            if ($response->successful()) {
                $result = $response->json();
                // Add the access code to the result for verification
                $result['accessCode'] = $accessCode;
                return $result;
            }

            Log::error('Failed to generate certificate', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);
            return null;
        } catch (\Exception $e) {
            Log::error('Exception when generating certificate', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }

    /**
     * Verify a certificate using its access code
     *
     * @param string $accessCode The access code of the certificate
     * @return array|null The verification result or null if failed
     */
    public function verifyCertificate(string $accessCode): ?array
    {
        if (!$this->service) {
            Log::error('Certificate generation service not configured');
            return null;
        }

        try {
            $response = Http::withToken($this->service->bearer_token)
                ->post($this->service->base_url . '/api/certificates/verify', [
                    'accessCode' => $accessCode
                ]);

            if ($response->successful()) {
                return $response->json();
            }

            Log::error('Failed to verify certificate', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);
            return null;
        } catch (\Exception $e) {
            Log::error('Exception when verifying certificate', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }

    /**
     * Get the download URL for a certificate
     *
     * @param string $accessCode The access code of the certificate
     * @return string|null The download URL or null if failed
     */
    public function getCertificateDownloadUrl(string $accessCode): ?string
    {
        if (!$this->service) {
            Log::error('Certificate generation service not configured');
            return null;
        }

        return $this->service->base_url . '/api/certificates/download/' . $accessCode;
    }

    /**
     * Get the preview URL for a certificate
     *
     * @param string $accessCode The access code of the certificate
     * @return string|null The preview URL or null if failed
     */
    public function getCertificatePreviewUrl(string $accessCode): ?string
    {
        if (!$this->service) {
            Log::error('Certificate generation service not configured');
            return null;
        }

        return $this->service->base_url . '/api/certificates/preview/' . $accessCode;
    }
}
