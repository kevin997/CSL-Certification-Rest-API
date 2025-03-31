<?php

namespace App\Services;

use App\Models\CertificateContent;
use App\Models\ActivityCompletion;
use App\Models\Enrollment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CertificateService extends ContentService
{
    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();
        
        $this->activityType = 'certificate';
        $this->modelClass = CertificateContent::class;
        
        $this->validationRules = [
            'activity_id' => 'required|integer|exists:activities,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'template' => 'required|string',
            'background_image' => 'nullable|string',
            'logo' => 'nullable|string',
            'signature_image' => 'nullable|string',
            'signatory_name' => 'nullable|string',
            'signatory_title' => 'nullable|string',
            'organization_name' => 'nullable|string',
            'certificate_text' => 'required|string',
            'completion_criteria' => 'required|array',
            'completion_criteria.required_activities' => 'boolean',
            'completion_criteria.minimum_score' => 'nullable|integer|min:0|max:100',
            'completion_criteria.minimum_time_spent' => 'nullable|integer|min:0',
            'expiry_period' => 'nullable|integer',
            'expiry_period_unit' => 'nullable|string|in:days,months,years',
            'verification_enabled' => 'boolean',
            'custom_fields' => 'nullable|array',
            'metadata' => 'nullable|array'
        ];
    }
    
    /**
     * Process data before saving to the database
     * Encode arrays to JSON
     *
     * @param array $data
     * @return array
     */
    protected function processDataBeforeSave(array $data): array
    {
        if (isset($data['completion_criteria']) && is_array($data['completion_criteria'])) {
            $data['completion_criteria'] = json_encode($data['completion_criteria']);
        }
        
        if (isset($data['custom_fields']) && is_array($data['custom_fields'])) {
            $data['custom_fields'] = json_encode($data['custom_fields']);
        }
        
        if (isset($data['metadata']) && is_array($data['metadata'])) {
            $data['metadata'] = json_encode($data['metadata']);
        }
        
        return $data;
    }
    
    /**
     * Process data after retrieving from the database
     * Decode JSON to arrays
     *
     * @param Model $model
     * @return Model
     */
    protected function processDataAfterRetrieve(Model $model): Model
    {
        if (isset($model->completion_criteria) && is_string($model->completion_criteria)) {
            $model->completion_criteria = json_decode($model->completion_criteria, true);
        }
        
        if (isset($model->custom_fields) && is_string($model->custom_fields)) {
            $model->custom_fields = json_decode($model->custom_fields, true);
        }
        
        if (isset($model->metadata) && is_string($model->metadata)) {
            $model->metadata = json_decode($model->metadata, true);
        }
        
        return $model;
    }
    
    /**
     * Get certificate content by ID with decoded data
     *
     * @param int $id
     * @return Model|null
     */
    public function getCertificate(int $id): ?Model
    {
        $content = $this->getById($id);
        
        if ($content) {
            return $this->processDataAfterRetrieve($content);
        }
        
        return null;
    }
    
    /**
     * Generate a certificate for a user
     *
     * @param int $certificateId
     * @param int $enrollmentId
     * @return array
     */
    public function generateCertificate(int $certificateId, int $enrollmentId): array
    {
        $certificate = $this->getCertificate($certificateId);
        
        if (!$certificate) {
            return [
                'success' => false,
                'message' => 'Certificate not found'
            ];
        }
        
        $enrollment = Enrollment::with(['user', 'course', 'activityCompletions'])
            ->find($enrollmentId);
        
        if (!$enrollment) {
            return [
                'success' => false,
                'message' => 'Enrollment not found'
            ];
        }
        
        // Check if user meets completion criteria
        $meetsCompletionCriteria = $this->checkCompletionCriteria($certificate, $enrollment);
        
        if (!$meetsCompletionCriteria['meets_criteria']) {
            return [
                'success' => false,
                'message' => 'User does not meet completion criteria: ' . $meetsCompletionCriteria['reason']
            ];
        }
        
        // Get activity ID
        $activityId = $certificate->activity_id;
        
        // Check if certificate already exists
        $existing = ActivityCompletion::where('enrollment_id', $enrollmentId)
            ->where('activity_id', $activityId)
            ->first();
        
        if ($existing && $existing->status === 'completed') {
            return [
                'success' => true,
                'message' => 'Certificate already generated',
                'certificate_data' => json_decode($existing->data ?? '{}', true)
            ];
        }
        
        // Generate certificate data
        $certificateData = $this->prepareCertificateData($certificate, $enrollment);
        
        // Create or update certificate record
        if ($existing) {
            $existing->update([
                'status' => 'completed',
                'completed_at' => now(),
                'data' => json_encode($certificateData)
            ]);
            $completion = $existing;
        } else {
            $completion = ActivityCompletion::create([
                'enrollment_id' => $enrollmentId,
                'activity_id' => $activityId,
                'status' => 'completed',
                'completed_at' => now(),
                'data' => json_encode($certificateData)
            ]);
        }
        
        return [
            'success' => true,
            'message' => 'Certificate generated successfully',
            'certificate_data' => $certificateData
        ];
    }
    
    /**
     * Check if user meets completion criteria
     *
     * @param Model $certificate
     * @param Enrollment $enrollment
     * @return array
     */
    protected function checkCompletionCriteria(Model $certificate, Enrollment $enrollment): array
    {
        $criteria = $certificate->completion_criteria;
        
        // Check if course is completed
        if ($enrollment->status !== 'completed' && $criteria['required_activities'] === true) {
            return [
                'meets_criteria' => false,
                'reason' => 'Course is not completed'
            ];
        }
        
        // Check minimum score if specified
        if (isset($criteria['minimum_score']) && $criteria['minimum_score'] > 0) {
            $averageScore = $enrollment->activityCompletions
                ->where('status', 'completed')
                ->avg('score') ?? 0;
            
            if ($averageScore < $criteria['minimum_score']) {
                return [
                    'meets_criteria' => false,
                    'reason' => "Average score ({$averageScore}) is below minimum required score ({$criteria['minimum_score']})"
                ];
            }
        }
        
        // Check minimum time spent if specified
        if (isset($criteria['minimum_time_spent']) && $criteria['minimum_time_spent'] > 0) {
            $totalTimeSpent = $enrollment->activityCompletions->sum('time_spent') ?? 0;
            
            if ($totalTimeSpent < $criteria['minimum_time_spent']) {
                return [
                    'meets_criteria' => false,
                    'reason' => "Total time spent ({$totalTimeSpent} minutes) is below minimum required time ({$criteria['minimum_time_spent']} minutes)"
                ];
            }
        }
        
        return [
            'meets_criteria' => true
        ];
    }
    
    /**
     * Prepare certificate data
     *
     * @param Model $certificate
     * @param Enrollment $enrollment
     * @return array
     */
    protected function prepareCertificateData(Model $certificate, Enrollment $enrollment): array
    {
        $user = $enrollment->user;
        $course = $enrollment->course;
        
        // Generate certificate number
        $certificateNumber = $this->generateCertificateNumber();
        
        // Calculate expiry date if applicable
        $expiryDate = null;
        if (isset($certificate->expiry_period) && $certificate->expiry_period > 0) {
            $expiryDate = now()->add($certificate->expiry_period_unit, $certificate->expiry_period)->format('Y-m-d');
        }
        
        // Generate verification code if enabled
        $verificationCode = null;
        if ($certificate->verification_enabled) {
            $verificationCode = Str::random(16);
        }
        
        // Prepare certificate text with placeholders replaced
        $certificateText = $certificate->certificate_text;
        $certificateText = str_replace('{student_name}', $user->name, $certificateText);
        $certificateText = str_replace('{course_name}', $course->title, $certificateText);
        $certificateText = str_replace('{completion_date}', now()->format('F j, Y'), $certificateText);
        $certificateText = str_replace('{certificate_number}', $certificateNumber, $certificateText);
        
        return [
            'certificate_number' => $certificateNumber,
            'student_name' => $user->name,
            'student_email' => $user->email,
            'course_name' => $course->title,
            'issue_date' => now()->format('Y-m-d'),
            'expiry_date' => $expiryDate,
            'verification_code' => $verificationCode,
            'certificate_text' => $certificateText,
            'signatory_name' => $certificate->signatory_name,
            'signatory_title' => $certificate->signatory_title,
            'organization_name' => $certificate->organization_name,
            'template' => $certificate->template,
            'background_image' => $certificate->background_image,
            'logo' => $certificate->logo,
            'signature_image' => $certificate->signature_image,
            'custom_fields' => $certificate->custom_fields ?? []
        ];
    }
    
    /**
     * Generate a unique certificate number
     *
     * @return string
     */
    protected function generateCertificateNumber(): string
    {
        $prefix = 'CERT';
        $timestamp = now()->format('YmdHis');
        $random = Str::random(6);
        
        return $prefix . '-' . $timestamp . '-' . $random;
    }
    
    /**
     * Verify a certificate
     *
     * @param string $certificateNumber
     * @param string $verificationCode
     * @return array
     */
    public function verifyCertificate(string $certificateNumber, string $verificationCode): array
    {
        // Find all activity completions
        $completions = ActivityCompletion::where('status', 'completed')->get();
        
        foreach ($completions as $completion) {
            $data = json_decode($completion->data ?? '{}', true);
            
            if (isset($data['certificate_number']) && 
                $data['certificate_number'] === $certificateNumber && 
                isset($data['verification_code']) && 
                $data['verification_code'] === $verificationCode) {
                
                // Check if certificate is expired
                $isExpired = false;
                if (isset($data['expiry_date']) && !empty($data['expiry_date'])) {
                    $isExpired = strtotime($data['expiry_date']) < time();
                }
                
                return [
                    'success' => true,
                    'valid' => !$isExpired,
                    'expired' => $isExpired,
                    'certificate_data' => $data,
                    'issue_date' => $data['issue_date'] ?? null,
                    'expiry_date' => $data['expiry_date'] ?? null
                ];
            }
        }
        
        return [
            'success' => false,
            'valid' => false,
            'message' => 'Certificate not found or verification code is invalid'
        ];
    }
    
    /**
     * Get all certificates for a user
     *
     * @param int $userId
     * @return array
     */
    public function getUserCertificates(int $userId): array
    {
        $enrollments = Enrollment::where('user_id', $userId)->pluck('id')->toArray();
        
        if (empty($enrollments)) {
            return [];
        }
        
        $certificates = [];
        
        $completions = ActivityCompletion::whereIn('enrollment_id', $enrollments)
            ->where('status', 'completed')
            ->with(['activity', 'enrollment.course'])
            ->get();
        
        foreach ($completions as $completion) {
            $data = json_decode($completion->data ?? '{}', true);
            
            if (isset($data['certificate_number'])) {
                $certificates[] = [
                    'completion_id' => $completion->id,
                    'course' => $completion->enrollment->course->title,
                    'activity' => $completion->activity->title,
                    'certificate_data' => $data,
                    'issue_date' => $data['issue_date'] ?? null,
                    'expiry_date' => $data['expiry_date'] ?? null,
                    'is_expired' => isset($data['expiry_date']) && !empty($data['expiry_date']) ? 
                        strtotime($data['expiry_date']) < time() : false
                ];
            }
        }
        
        return $certificates;
    }
    
    /**
     * Revoke a certificate
     *
     * @param int $completionId
     * @return bool
     */
    public function revokeCertificate(int $completionId): bool
    {
        $completion = ActivityCompletion::find($completionId);
        
        if (!$completion || $completion->status !== 'completed') {
            return false;
        }
        
        $data = json_decode($completion->data ?? '{}', true);
        
        if (!isset($data['certificate_number'])) {
            return false;
        }
        
        // Mark as revoked
        $data['revoked'] = true;
        $data['revoked_at'] = now()->format('Y-m-d H:i:s');
        
        $completion->update([
            'data' => json_encode($data)
        ]);
        
        return true;
    }
    
    /**
     * Update certificate template
     *
     * @param int $id
     * @param string $template
     * @return Model|null
     */
    public function updateTemplate(int $id, string $template): ?Model
    {
        return $this->update($id, ['template' => $template]);
    }
    
    /**
     * Update certificate images
     *
     * @param int $id
     * @param array $images
     * @return Model|null
     */
    public function updateImages(int $id, array $images): ?Model
    {
        $updateData = [];
        
        if (isset($images['background_image'])) {
            $updateData['background_image'] = $images['background_image'];
        }
        
        if (isset($images['logo'])) {
            $updateData['logo'] = $images['logo'];
        }
        
        if (isset($images['signature_image'])) {
            $updateData['signature_image'] = $images['signature_image'];
        }
        
        if (empty($updateData)) {
            return null;
        }
        
        return $this->update($id, $updateData);
    }
    
    /**
     * Update certificate completion criteria
     *
     * @param int $id
     * @param array $criteria
     * @return Model|null
     */
    public function updateCompletionCriteria(int $id, array $criteria): ?Model
    {
        return $this->update($id, ['completion_criteria' => $criteria]);
    }
    
    /**
     * Add custom field to certificate
     *
     * @param int $id
     * @param array $field
     * @return Model|null
     */
    public function addCustomField(int $id, array $field): ?Model
    {
        $certificate = $this->getCertificate($id);
        
        if (!$certificate) {
            return null;
        }
        
        $customFields = $certificate->custom_fields ?? [];
        $customFields[] = $field;
        
        return $this->update($id, ['custom_fields' => $customFields]);
    }
    
    /**
     * Remove custom field from certificate
     *
     * @param int $id
     * @param string $fieldName
     * @return Model|null
     */
    public function removeCustomField(int $id, string $fieldName): ?Model
    {
        $certificate = $this->getCertificate($id);
        
        if (!$certificate || !isset($certificate->custom_fields)) {
            return null;
        }
        
        $customFields = array_filter($certificate->custom_fields, function ($field) use ($fieldName) {
            return $field['name'] !== $fieldName;
        });
        
        return $this->update($id, ['custom_fields' => array_values($customFields)]);
    }
}
