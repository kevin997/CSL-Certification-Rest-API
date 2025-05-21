<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CertificateContent;
use App\Services\CertificateGenerationService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;

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
    public function generate(Request $request, $activityId, $id)
    {
        // Find certificate content
        $certificateContent = CertificateContent::where('activity_id', $activityId)
            ->where('id', $id)
            ->firstOrFail();

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

        // Prepare user data
        $userData = [
            'fullName' => $request->fullName,
            'certificateDate' => $request->certificateDate ?? now()->format('F j, Y'),
        ];

        // Add any additional data
        if ($request->has('additionalData') && is_array($request->additionalData)) {
            $userData = array_merge($userData, $request->additionalData);
        }

        // Generate certificate
        $result = $this->certificateGenerationService->generateCertificate($certificateContent, $userData);

        if (!$result) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to generate certificate',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        // Return success response with certificate data
        return response()->json([
            'status' => 'success',
            'message' => 'Certificate generated successfully',
            'data' => [
                'fileUrl' => $this->certificateGenerationService->getCertificateDownloadUrl($result['accessCode']),
                'previewUrl' => $this->certificateGenerationService->getCertificatePreviewUrl($result['accessCode']),
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
}
