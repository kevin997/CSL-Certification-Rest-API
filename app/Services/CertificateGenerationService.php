<?php

namespace App\Services;

use App\Models\CertificateContent;
use App\Models\CertificateTemplate;
use App\Models\Enrollment;
use App\Models\ThirdPartyService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Events\CertificateIssued;

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

        // Authenticate with the service if we have credentials but no token
        if ($this->service && !$this->service->bearer_token && $this->service->username && $this->service->password) {
            $this->authenticate();
        }
    }

    /**
     * Authenticate with the certificate service and store the bearer token
     *
     * @return bool True if authentication was successful, false otherwise
     */
    public function authenticate(): bool
    {
        if (!$this->service) {
            Log::error('Certificate generation service not configured');
            return false;
        }

        try {
            // Get config from service
            $config = json_decode($this->service->config ?? '{}', true);
            $verifySSL = $config['verify_ssl'] ?? false;

            // Disable SSL verification for the request
            $response = Http::withOptions([
                'verify' => $verifySSL,
            ])->post($this->service->base_url . '/api/login', [
                'email' => $this->service->username,
                'password' => $this->service->password,
            ]);

            if ($response->successful() && isset($response['data']['access_token'])) {
                // Update the service with the new token
                $this->service->bearer_token = $response['data']['access_token'];
                $this->service->save();

                Log::info('Successfully authenticated with certificate service');
                return true;
            }

            Log::error('Failed to authenticate with certificate service: ' . $response->body());
            return false;
        } catch (\Exception $e) {
            Log::error('Error authenticating with certificate service: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Make an authenticated API call to the certificate service with automatic re-authentication on 401
     *
     * @param string $method HTTP method (get, post, put, delete)
     * @param string $endpoint API endpoint (without base URL)
     * @param array $data Request data
     * @param array $files Files to attach to the request
     * @return \Illuminate\Http\Client\Response|null Response or null if failed
     */
    protected function makeAuthenticatedRequest(string $method, string $endpoint, array $data = [], array $files = []): ?\Illuminate\Http\Client\Response
    {
        if (!$this->service) {
            Log::error('Certificate generation service not configured');
            return null;
        }

        $url = $this->service->base_url . '/api/' . ltrim($endpoint, '/');

        try {
            // Get config from service
            $config = json_decode($this->service->config ?? '{}', true);
            $verifySSL = $config['verify_ssl'] ?? false;

            // Prepare the request with SSL verification disabled
            $request = Http::withOptions([
                'verify' => $verifySSL,
            ])->withToken($this->service->bearer_token);

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
                    ])->withToken($this->service->bearer_token);

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
            Log::error("Error making {$method} request to {$endpoint}: " . $e->getMessage());
            return null;
        }
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
            $response = $this->makeAuthenticatedRequest(
                'post',
                'templates/upload',
                ['name' => $name],
                ['template' => $file]
            );

            if ($response && $response->successful()) {
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
            $response = $this->makeAuthenticatedRequest(
                'delete',
                'templates/' . $filename
            );

            if ($response && $response->successful()) {
                return true;
            }

            Log::error('Failed to delete template from certificate service', [
                'status' => $response ? $response->status() : 'No response',
                'body' => $response ? $response->body() : 'No response'
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
     * @param Enrollment $enrollment The enrollment record for the user
     */
    public function generateCertificate(CertificateContent $certificateContent, array $userData, ?string $templateName = null, ?Enrollment $enrollment = null): ?array
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

            // Generate a unique access code for this certificate
            $accessCode = $this->generateAccessCode();

            // Determine which template to use
            $templateToUse = $templateName ?: $certificateContent->template->file_path ?? 'default';

            Log::info('Enrollment course title: ' . ($enrollment && $enrollment->course ? $enrollment->course->title : 'No course title'));
            Log::info('Certificate content course title: ' . $this->getTemplateTitleFromCertificateContent($certificateContent));
            // Prepare only the required data for the certificate microservice
            $certificateData = [
                'template_name' => $templateToUse,
                'data' => [
                    'fullName' => $userData['fullName'] ?? 'Student Name',
                    'courseTitle' => $enrollment && $enrollment->course ? $enrollment->course->title : ($this->getTemplateTitleFromCertificateContent($certificateContent) ?: 'Certificate'),
                    'certificateDate' => $userData['certificateDate'] ?? now()->format('F j, Y'),
                    'expiryDate' => $userData['expiryDate'] ?? null,
                    'accessCode' => $accessCode,
                ]
            ];

            Log::info('Certificate data for generateCertificate before request: '.json_encode($certificateData));

            // Make the authenticated request to generate the certificate
            $response = $this->makeAuthenticatedRequest(
                'post',
                'certificates/generate',
                $certificateData
            );

            if ($response && $response->successful()) {
                $result = $response->json();

                // Check if the response contains the certificate URLs
                if (isset($result['data']['certificate_url'])) {


                    // Add the access code and certificate data to the result for the client
                    $result['accessCode'] = $accessCode;
                    
                    if (!$enrollment) {
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
     * @param CertificateContent $certificateContent The certificate content
     * @return string|null The download URL or null if failed
     */
    public function getCertificateDownloadUrl(CertificateContent $certificateContent): ?string
    {
        if (!$this->service) {
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
            return $this->service->base_url . '/api/certificates/download/' . $accessCode;
        }

        return null;
    }

    /**
     * Get the preview URL for a certificate
     *
     * @param CertificateContent $certificateContent The certificate content
     * @return string|null The preview URL or null if failed
     */
    public function getCertificatePreviewUrl(CertificateContent $certificateContent): ?string
    {
        if (!$this->service) {
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
            return $this->service->base_url . '/api/certificates/preview/' . $path;
        }

        // Fallback to using access code if certificate_url is not available
        $accessCode = $metadata['access_code'] ?? null;
        if ($accessCode) {
            return $this->service->base_url . '/api/certificates/preview/' . $accessCode;
        }

        return null;
    }

    /**
     * Get a URL for the certificate service with the given path
     *
     * @param string $path The path to append to the base URL
     * @return string|null The full URL or null if the service is not configured
     */
    public function getServiceUrl(string $path): ?string
    {
        if (!$this->service) {
            Log::error('Certificate generation service not configured');
            return null;
        }

        return $this->service->base_url . $path;
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
     * @param CertificateContent $certificateContent The certificate content
     * @param array $userData The user data for the certificate
     * @param string $accessCode The unique access code for the certificate
     * @param string $certificateUrl The URL to the generated certificate
     * @param string|null $previewUrl The URL to the certificate preview (optional)
     * @return \App\Models\IssuedCertificate|null The created IssuedCertificate or null if failed
     */
    protected function createIssuedCertificate(
        CertificateContent $certificateContent,
        array $userData,
        string $accessCode,
        string $certificateUrl,
        ?string $previewUrl = null,
    ): ?\App\Models\IssuedCertificate {
        try {
            // Import the IssuedCertificate model
            $issuedCertificateClass = '\App\Models\IssuedCertificate';

            // Create the IssuedCertificate record
            $issuedCertificate = new $issuedCertificateClass();
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
                'certificate_date' => $userData['certificateDate'] ?? now()->format('F j, Y')
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
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }


    /**
     * Get the template title from certificate content by traversing the relationship chain
     * CertificateContent -> Activity -> Block -> Template
     *
     * @param CertificateContent $certificateContent
     * @return string|null
     */
    protected function getTemplateTitleFromCertificateContent(CertificateContent $certificateContent): ?string
    {
        try {
            // Get the activity by ID instead of using the morphOne relationship
            if (!$certificateContent->activity_id) {
                Log::info('No activity_id found in certificate content');
                return null;
            }

            $activity = \App\Models\Activity::find($certificateContent->activity_id);

            if (!$activity) {
                Log::info('Activity not found with ID: ' . $certificateContent->activity_id);
                return null;
            }

            Log::info('Activity found: ' . ($activity->title ?? 'No title'));

            // Get the block associated with the activity
            if (!$activity->block_id) {
                Log::info('No block_id found in activity');
                return null;
            }

            $block = \App\Models\Block::find($activity->block_id);

            if (!$block) {
                Log::info('Block not found with ID: ' . $activity->block_id);
                return null;
            }

            // Get the template associated with the block
            if (!$block->template_id) {
                Log::info('No template_id found in block');
                return null;
            }

            $template = \App\Models\Template::find($block->template_id);

            if (!$template) {
                Log::info('Template not found with ID: ' . $block->template_id);
                return null;
            }

            Log::info('Template title found: ' . ($template->title ?? 'No title'));
            return $template->title;
        } catch (\Exception $e) {
            Log::error('Failed to get template title from certificate content', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }
}
