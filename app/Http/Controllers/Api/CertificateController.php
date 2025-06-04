<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CertificateContent;
use App\Models\IssuedCertificate;
use App\Services\CertificateGenerationService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class CertificateController extends Controller
{
    /**
     * Certificate generation service
     * 
     * @var CertificateGenerationService
     */
    protected $certificateGenerationService;
    
    /**
     * Constructor
     * 
     * @param CertificateGenerationService $certificateGenerationService
     */
    public function __construct(CertificateGenerationService $certificateGenerationService)
    {
        $this->certificateGenerationService = $certificateGenerationService;
    }
    
    /**
     * Download a certificate
     * 
     * @param Request $request
     * @param string $path
     * @return Response
     */
    public function download(Request $request, string $path)
    {
        // Verify that the request has a valid signature
        if (!$request->hasValidSignature()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid signature',
            ], Response::HTTP_UNAUTHORIZED);
        }
        
        // Proxy the request to the certificate service
        $serviceUrl = $this->certificateGenerationService->getServiceUrl('/api/certificates/download/' . $path);
        
        if (!$serviceUrl) {
            return response()->json([
                'status' => 'error',
                'message' => 'Certificate service not configured',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
        
        // Redirect to the certificate service URL
        return redirect()->away($serviceUrl);
    }
    
    /**
     * Preview a certificate
     * 
     * @param Request $request
     * @param string $path
     * @return Response
     */
    public function preview(Request $request, string $path)
    {
        // Proxy the request to the certificate service
        $serviceUrl = $this->certificateGenerationService->getServiceUrl('/api/certificates/preview/' . $path);
        
        if (!$serviceUrl) {
            return response()->json([
                'status' => 'error',
                'message' => 'Certificate service not configured',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
        
        // Redirect to the certificate service URL
        return redirect()->away($serviceUrl);
    }

    /**
     * Generate a certificate for a user
     * 
     * @param Request $request
     * @param int $activityId
     * @param int $id
     * @return Response
     * 
     * @OA\Post(
     *     path="/activities/{activityId}/certificate-content/{id}/generate",
     *     summary="Generate a certificate for a user",
     *     description="Generates a certificate for a user based on the certificate content",
     *     operationId="generateCertificate",
     *     tags={"Certificates"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="activityId",
     *         in="path",
     *         required=true,
     *         description="ID of the activity",
     *         @OA\Schema(type="integer", format="int64")
     *     ),
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the certificate content",
     *         @OA\Schema(type="integer", format="int64")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         description="User data for certificate",
     *         @OA\JsonContent(
     *             required={"fullName"},
     *             @OA\Property(property="fullName", type="string", example="John Doe"),
     *             @OA\Property(property="certificateDate", type="string", example="May 21, 2025"),
     *             @OA\Property(property="additionalData", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Certificate generated successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Certificate generated successfully"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="fileUrl", type="string", example="https://example.com/certificates/abc123.pdf"),
     *                 @OA\Property(property="previewUrl", type="string", example="https://example.com/certificates/preview/abc123"),
     *                 @OA\Property(property="accessCode", type="string", example="ABC123XYZ")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Certificate content not found"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     )
     * )
     */
    public function generate(Request $request, $activityId, $id = null)
    {
        // Find certificate content
        // If ID is provided, try to find by both activity_id and id
        if ($id) {
            $certificateContent = CertificateContent::where('activity_id', $activityId)
                ->where('id', $id)
                ->first();
        }
        
        // If no certificate content found or ID not provided, find by activity_id only
        if (empty($certificateContent)) {
            $certificateContent = CertificateContent::where('activity_id', $activityId)
                ->latest()
                ->firstOrFail();
        }

        // Validate request
        $validator = Validator::make($request->all(), [
            'fullName' => 'required|string|max:100',
            'certificateDate' => 'nullable|string|max:50',
            'additionalData' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }


        // Calculate certificate date based on expiry period if set
        $issueDate = now();
        $expiryDate = null;

        // If expiry period is set, calculate the expiry date
        if ($certificateContent->expiry_period && $certificateContent->expiry_period_unit) {
            $expiryDate = clone $issueDate;

            switch ($certificateContent->expiry_period_unit) {
                case 'days':
                    $expiryDate->addDays($certificateContent->expiry_period);
                    break;
                case 'months':
                    $expiryDate->addMonths($certificateContent->expiry_period);
                    break;
                case 'years':
                    $expiryDate->addYears($certificateContent->expiry_period);
                    break;
                default:
                    // Default to no expiry if unit is not recognized
                    $expiryDate = null;
            }
        }

        // Prepare user data
        $userData = [
            'fullName' => $request->fullName,
            'certificateDate' => $request->certificateDate ?? now()->format('F j, Y'),
            'expiryDate' => $expiryDate->format('F j, Y') ?? null,
        ];

        // Add any additional data
        if ($request->has('additionalData') && is_array($request->additionalData)) {
            $userData = array_merge($userData, $request->additionalData);
        }

        // Generate certificate through the certificate generation service
        $templateName = $certificateContent->template->file_path;
        // Strip the 'templates/' prefix if it exists
        $templateName = str_replace('templates/', '', $templateName);
        Log::info('Template name on Rest api: '.$templateName);
        $result = $this->certificateGenerationService->generateCertificate(
            $certificateContent,
            $userData,
            $templateName,
        );

        if (!$result) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to generate certificate',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
        
        // We'll use the original certificate content object since it's already been updated in the database
        // The metadata is stored in the database but not in our current object, so we need to refresh it
        $certificateContent->refresh();

        return response()->json([
            'status' => 'success',
            'message' => 'Certificate generated successfully',
            'data' => [
                'fileUrl' => $this->certificateGenerationService->getCertificateDownloadUrl($certificateContent),
                'previewUrl' => $this->certificateGenerationService->getCertificatePreviewUrl($certificateContent),
                'accessCode' => $result['accessCode'],
            ],
        ]);
    }

    /**
     * Verify a certificate
     * 
     * @param Request $request
     * @return Response
     * 
     * @OA\Post(
     *     path="/certificates/verify",
     *     summary="Verify a certificate",
     *     description="Verifies a certificate using its access code",
     *     operationId="verifyCertificate",
     *     tags={"Certificates"},
     *     @OA\RequestBody(
     *         required=true,
     *         description="Certificate verification data",
     *         @OA\JsonContent(
     *             required={"accessCode"},
     *             @OA\Property(property="accessCode", type="string", example="ABC123XYZ")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Certificate verification result",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="isValid", type="boolean", example=true),
     *                 @OA\Property(property="certificateData", type="object")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     )
     * )
     */
    public function verify(Request $request)
    {
        // Validate request
        $validator = Validator::make($request->all(), [
            'accessCode' => 'required|string|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Verify certificate
        $result = $this->certificateGenerationService->verifyCertificate($request->accessCode);

        if (!$result) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to verify certificate',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return response()->json([
            'status' => 'success',
            'data' => $result,
        ]);
    }
    
    /**
     * Issue a certificate for a user and store it in the IssuedCertificate model
     * 
     * @param Request $request
     * @param int $certificateContentId
     * @return Response
     * 
     * @OA\Post(
     *     path="/certificate-content/{certificateContentId}/issue",
     *     summary="Issue a certificate for a user",
     *     description="Issues a certificate for a user and stores it in the IssuedCertificate model",
     *     operationId="issueCertificate",
     *     tags={"Certificates"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="certificateContentId",
     *         in="path",
     *         required=true,
     *         description="ID of the certificate content",
     *         @OA\Schema(type="integer", format="int64")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         description="User data for certificate",
     *         @OA\JsonContent(
     *             required={"fullName"},
     *             @OA\Property(property="fullName", type="string", example="John Doe"),
     *             @OA\Property(property="certificateDate", type="string", example="May 21, 2025"),
     *             @OA\Property(property="additionalData", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Certificate issued successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Certificate issued successfully"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="fileUrl", type="string"),
     *                 @OA\Property(property="previewUrl", type="string"),
     *                 @OA\Property(property="accessCode", type="string"),
     *                 @OA\Property(property="issuedCertificateId", type="integer")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Certificate content not found"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     )
     * )
     */
    public function issueCertificate(Request $request, $certificateContentId)
    {
        // Find the certificate content
        $certificateContent = CertificateContent::with('template')->findOrFail($certificateContentId);
        
        // Get the current authenticated user
        $user = Auth::user();
        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'User not authenticated',
            ], Response::HTTP_UNAUTHORIZED);
        }
        
        // Find the correct course for this certificate content and user
        $activity = \App\Models\Activity::find($certificateContent->activity_id);
        if (!$activity) {
            return response()->json([
                'status' => 'error',
                'message' => 'Activity not found for certificate content',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $block = $activity->block;
        if (!$block) {
            return response()->json([
                'status' => 'error',
                'message' => 'Block not found for activity',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $template = $block->template;
        if (!$template) {
            return response()->json([
                'status' => 'error',
                'message' => 'Template not found for block',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $courses = $template->courses;
        if ($courses->isEmpty()) {
            return response()->json([
                'status' => 'error',
                'message' => 'No courses found for template',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        // Find the user's enrollment in any of these courses
        $enrollment = \App\Models\Enrollment::where('user_id', $user->id)
            ->whereIn('course_id', $courses->pluck('id'))
            ->first();
        if (!$enrollment) {
            return response()->json([
                'status' => 'error',
                'message' => 'User is not enrolled in any course for this certificate',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $courseId = $enrollment->course_id;
        
        // Calculate certificate date based on expiry period if set
        $issueDate = now();
        $expiryDate = null;
        
        // If expiry period is set, calculate the expiry date
        if ($certificateContent->expiry_period && $certificateContent->expiry_period_unit) {
            $expiryDate = clone $issueDate;
            
            switch ($certificateContent->expiry_period_unit) {
                case 'days':
                    $expiryDate->addDays($certificateContent->expiry_period);
                    break;
                case 'months':
                    $expiryDate->addMonths($certificateContent->expiry_period);
                    break;
                case 'years':
                    $expiryDate->addYears($certificateContent->expiry_period);
                    break;
                default:
                    // Default to no expiry if unit is not recognized
                    $expiryDate = null;
            }
        }
        
        // Prepare user data from the authenticated user and certificate content
        $userData = [
            'fullName' => $user->name,
            'certificateDate' => $issueDate->format('F j, Y'),
            'user_id' => $user->id,
            'course_id' => $courseId,
            'email' => $user->email,
            'issued_date' => $issueDate->toDateTimeString(),
            'expiry_date' => $expiryDate ? $expiryDate->toDateTimeString() : null,
            'expiryDate' =>  $expiryDate->format('F j, Y') ?? null,
        ];
       
        // Add certificate content metadata
        $userData = array_merge($userData, [
            'certificate_id' => $certificateContent->id,
            'certificate_title' => $certificateContent->title,
        ]);

        // Get the template name from the relationship
        $templateName = $certificateContent->template ? $certificateContent->template->file_path : null;
        // Strip the 'templates/' prefix if it exists
        if ($templateName) {
            $templateName = str_replace('templates/', '', $templateName);
        }
        Log::info('Template name for issueCertificate: '.$templateName);

        // Generate certificate through the certificate generation service
        $result = $this->certificateGenerationService->generateCertificate(
            $certificateContent,
            $userData,
            $templateName,
            $enrollment
        );

        if (!$result) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to issue certificate',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
        
        // Find the issued certificate that was created during generation
        $issuedCertificate = null;
        if (isset($result['issuedCertificateId'])) {
            $issuedCertificate = IssuedCertificate::find($result['issuedCertificateId']);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Certificate issued successfully',
            'data' => [
                'fileUrl' => $this->certificateGenerationService->getCertificateDownloadUrl($certificateContent),
                'previewUrl' => $this->certificateGenerationService->getCertificatePreviewUrl($certificateContent),
                'accessCode' => $result['accessCode'],
                'issuedCertificateId' => $issuedCertificate ? $issuedCertificate->id : null,
            ],
        ]);
    }
}
