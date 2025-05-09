<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Block;
use App\Models\CertificateContent;
use App\Models\Template;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

/**
 * @OA\Schema(
 *     schema="CertificateContent",
 *     required={"activity_id", "title", "description", "template_type"},
 *     @OA\Property(property="id", type="integer", format="int64", example=1),
 *     @OA\Property(property="activity_id", type="integer", format="int64", example=1),
 *     @OA\Property(property="title", type="string", example="CSL Certification of Completion"),
 *     @OA\Property(property="description", type="string", example="This certificate is awarded for successful completion of the CSL Certification Program"),
 *     @OA\Property(property="template_type", type="string", enum={"completion", "achievement", "participation", "custom"}, example="completion"),
 *     @OA\Property(property="background_image", type="string", example="certificates/backgrounds/standard.jpg", nullable=true),
 *     @OA\Property(property="logo", type="string", example="certificates/logos/csl-logo.png", nullable=true),
 *     @OA\Property(property="signature_image", type="string", example="certificates/signatures/director.png", nullable=true),
 *     @OA\Property(property="signatory_name", type="string", example="Dr. Jane Smith", nullable=true),
 *     @OA\Property(property="signatory_title", type="string", example="Program Director", nullable=true),
 *     @OA\Property(property="accent_color", type="string", example="#336699", nullable=true),
 *     @OA\Property(
 *         property="custom_fields",
 *         type="array",
 *         @OA\Items(
 *             type="object",
 *             @OA\Property(property="name", type="string", example="Course Duration"),
 *             @OA\Property(property="value", type="string", example="120 Hours"),
 *             @OA\Property(property="position", type="string", enum={"header", "body", "footer"}, example="body")
 *         )
 *     ),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 */

class CertificateContentController extends Controller
{
    /**
     * Store a newly created certificate content in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $activityId
     * @return \Illuminate\Http\Response
     * 
     * @OA\Post(
     *     path="/activities/{activityId}/certificate-content",
     *     summary="Create certificate content for an activity",
     *     description="Creates new certificate content for a certificate-type activity",
     *     operationId="storeCertificateContent",
     *     tags={"Content Types"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="activityId",
     *         in="path",
     *         required=true,
     *         description="ID of the activity",
     *         @OA\Schema(type="integer", format="int64")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         description="Certificate content data",
     *         @OA\JsonContent(
     *             required={"title", "description", "template_type"},
     *             @OA\Property(property="title", type="string", example="CSL Certification of Completion"),
     *             @OA\Property(property="description", type="string", example="This certificate is awarded for successful completion of the CSL Certification Program"),
     *             @OA\Property(property="template_type", type="string", enum={"completion", "achievement", "participation", "custom"}, example="completion"),
     *             @OA\Property(property="background_image", type="string", example="certificates/backgrounds/standard.jpg", nullable=true),
     *             @OA\Property(property="logo", type="string", example="certificates/logos/csl-logo.png", nullable=true),
     *             @OA\Property(property="signature_image", type="string", example="certificates/signatures/director.png", nullable=true),
     *             @OA\Property(property="signatory_name", type="string", example="Dr. Jane Smith", nullable=true),
     *             @OA\Property(property="signatory_title", type="string", example="Program Director", nullable=true),
     *             @OA\Property(property="accent_color", type="string", example="#336699", nullable=true),
     *             @OA\Property(
     *                 property="custom_fields",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="name", type="string", example="Course Duration"),
     *                     @OA\Property(property="value", type="string", example="120 Hours"),
     *                     @OA\Property(property="position", type="string", enum={"header", "body", "footer"}, example="body")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Certificate content created successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Certificate content created successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/CertificateContent")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid activity type"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - User does not have permission"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Activity not found"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     )
     * )
     */
    public function store(Request $request, $activityId)
    {
        $activity = Activity::findOrFail($activityId);
        $block = Block::findOrFail($activity->block_id);
        $template = Template::findOrFail($block->template_id);

        // Check if user has permission to add content to this activity
        if ($template->created_by !== Auth::id()) {
            return response()->json([
                'status' => 'error',
                'message' => 'You do not have permission to add content to this activity',
            ], Response::HTTP_FORBIDDEN);
        }

        // Validate activity type
        if ($activity->type->value !== 'certificate') {
            return response()->json([
                'status' => 'error',
                'message' => 'This activity is not of type certificate',
            ], Response::HTTP_BAD_REQUEST);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'certificate_type' => 'required|string|in:completion,achievement,participation,custom',
            'template_design' => 'required|string|in:standard,premium,custom',
            'background_image_url' => 'nullable|string|url',
            'logo_url' => 'nullable|string|url',
            'signature_image_url' => 'nullable|string|url',
            'signatory_name' => 'required|string|max:255',
            'signatory_title' => 'required|string|max:255',
            'signatory_organization' => 'nullable|string|max:255',
            'custom_fields' => 'nullable|array',
            'custom_fields.*.name' => 'required_with:custom_fields|string|max:100',
            'custom_fields.*.value' => 'required_with:custom_fields|string|max:255',
            'custom_fields.*.display_on_certificate' => 'boolean',
            'completion_criteria' => 'required|array',
            'completion_criteria.type' => 'required|string|in:all_activities,percentage,specific_activities',
            'completion_criteria.value' => 'required_if:completion_criteria.type,percentage|nullable|integer|min:1|max:100',
            'completion_criteria.activities' => 'required_if:completion_criteria.type,specific_activities|nullable|array',
            'completion_criteria.activities.*' => 'integer|exists:activities,id',
            'expiry_period' => 'nullable|integer', // in days, null means never expires
            'allow_download' => 'boolean',
            'download_formats' => 'required_if:allow_download,true|array',
            'download_formats.*' => 'string|in:pdf,jpg,png',
            'allow_sharing' => 'boolean',
            'sharing_platforms' => 'required_if:allow_sharing,true|array',
            'sharing_platforms.*' => 'string|in:linkedin,facebook,twitter,email',
            'verification_enabled' => 'boolean',
            'verification_method' => 'required_if:verification_enabled,true|string|in:qr,link,code',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Check if certificate content already exists for this activity
        $existingContent = CertificateContent::where('activity_id', $activityId)->first();
        if ($existingContent) {
            return response()->json([
                'status' => 'error',
                'message' => 'Certificate content already exists for this activity',
            ], Response::HTTP_CONFLICT);
        }

        // Prepare data for storage
        $data = $request->except(['custom_fields', 'completion_criteria', 'download_formats', 'sharing_platforms']);
        
        // Handle arrays that need to be stored as JSON
        if ($request->has('custom_fields')) {
            $data['custom_fields'] = json_encode($request->custom_fields);
        }
        
        if ($request->has('completion_criteria')) {
            $data['completion_criteria'] = json_encode($request->completion_criteria);
        }
        
        if ($request->has('download_formats')) {
            $data['download_formats'] = json_encode($request->download_formats);
        }
        
        if ($request->has('sharing_platforms')) {
            $data['sharing_platforms'] = json_encode($request->sharing_platforms);
        }
        
        // Add activity_id to data
        $data['activity_id'] = $activityId;

        $certificateContent = CertificateContent::create($data);

        return response()->json([
            'status' => 'success',
            'message' => 'Certificate content created successfully',
            'data' => $certificateContent,
        ], Response::HTTP_CREATED);
    }

    /**
     * Display the specified certificate content.
     *
     * @param  int  $activityId
     * @return \Illuminate\Http\Response
     * 
     * @OA\Get(
     *     path="/activities/{activityId}/certificate-content",
     *     summary="Get certificate content for an activity",
     *     description="Retrieves certificate content for a certificate-type activity",
     *     operationId="getCertificateContent",
     *     tags={"Content Types"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="activityId",
     *         in="path",
     *         required=true,
     *         description="ID of the activity",
     *         @OA\Schema(type="integer", format="int64")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Certificate content retrieved successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="data", ref="#/components/schemas/CertificateContent")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - User does not have permission"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Activity not found"
     *     )
     * )
     */
    public function show($activityId)
    {
        $activity = Activity::findOrFail($activityId);
        $block = Block::findOrFail($activity->block_id);
        $template = Template::findOrFail($block->template_id);

        // Check if user has access to this template
        if (!$template->is_public && $template->created_by !== Auth::id()) {
            return response()->json([
                'status' => 'error',
                'message' => 'You do not have permission to view this content',
            ], Response::HTTP_FORBIDDEN);
        }

        $certificateContent = CertificateContent::where('activity_id', $activityId)->firstOrFail();
        
        // Decode JSON fields for the response
        if ($certificateContent->custom_fields) {
            $certificateContent->custom_fields = json_decode($certificateContent->custom_fields);
        }
        
        if ($certificateContent->completion_criteria) {
            $certificateContent->completion_criteria = json_decode($certificateContent->completion_criteria);
        }
        
        if ($certificateContent->download_formats) {
            $certificateContent->download_formats = json_decode($certificateContent->download_formats);
        }
        
        if ($certificateContent->sharing_platforms) {
            $certificateContent->sharing_platforms = json_decode($certificateContent->sharing_platforms);
        }

        return response()->json([
            'status' => 'success',
            'data' => $certificateContent,
        ]);
    }

    /**
     * Update the specified certificate content in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $activityId
     * @return \Illuminate\Http\Response
     * 
     * @OA\Put(
     *     path="/activities/{activityId}/certificate-content",
     *     summary="Update certificate content for an activity",
     *     description="Updates certificate content for a certificate-type activity",
     *     operationId="updateCertificateContent",
     *     tags={"Content Types"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="activityId",
     *         in="path",
     *         required=true,
     *         description="ID of the activity",
     *         @OA\Schema(type="integer", format="int64")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         description="Certificate content data",
     *         @OA\JsonContent(
     *             @OA\Property(property="title", type="string", example="CSL Certification of Completion"),
     *             @OA\Property(property="description", type="string", example="This certificate is awarded for successful completion of the CSL Certification Program"),
     *             @OA\Property(property="template_type", type="string", enum={"completion", "achievement", "participation", "custom"}, example="completion"),
     *             @OA\Property(property="background_image", type="string", example="certificates/backgrounds/standard.jpg", nullable=true),
     *             @OA\Property(property="logo", type="string", example="certificates/logos/csl-logo.png", nullable=true),
     *             @OA\Property(property="signature_image", type="string", example="certificates/signatures/director.png", nullable=true),
     *             @OA\Property(property="signatory_name", type="string", example="Dr. Jane Smith", nullable=true),
     *             @OA\Property(property="signatory_title", type="string", example="Program Director", nullable=true),
     *             @OA\Property(property="accent_color", type="string", example="#336699", nullable=true),
     *             @OA\Property(
     *                 property="custom_fields",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="name", type="string", example="Course Duration"),
     *                     @OA\Property(property="value", type="string", example="120 Hours"),
     *                     @OA\Property(property="position", type="string", enum={"header", "body", "footer"}, example="body")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Certificate content updated successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Certificate content updated successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/CertificateContent")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - User does not have permission"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Activity not found"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     )
     * )
     */
    public function update(Request $request, $activityId)
    {
        $activity = Activity::findOrFail($activityId);
        $block = Block::findOrFail($activity->block_id);
        $template = Template::findOrFail($block->template_id);

        // Check if user has permission to update this content
        if ($template->created_by !== Auth::id()) {
            return response()->json([
                'status' => 'error',
                'message' => 'You do not have permission to update this content',
            ], Response::HTTP_FORBIDDEN);
        }

        $certificateContent = CertificateContent::where('activity_id', $activityId)->firstOrFail();

        $validator = Validator::make($request->all(), [
            'title' => 'string|max:255',
            'description' => 'string',
            'certificate_type' => 'string|in:completion,achievement,participation,custom',
            'template_design' => 'string|in:standard,premium,custom',
            'background_image_url' => 'nullable|string|url',
            'logo_url' => 'nullable|string|url',
            'signature_image_url' => 'nullable|string|url',
            'signatory_name' => 'string|max:255',
            'signatory_title' => 'string|max:255',
            'signatory_organization' => 'nullable|string|max:255',
            'custom_fields' => 'nullable|array',
            'custom_fields.*.name' => 'required_with:custom_fields|string|max:100',
            'custom_fields.*.value' => 'required_with:custom_fields|string|max:255',
            'custom_fields.*.display_on_certificate' => 'boolean',
            'completion_criteria' => 'array',
            'completion_criteria.type' => 'string|in:all_activities,percentage,specific_activities',
            'completion_criteria.value' => 'required_if:completion_criteria.type,percentage|nullable|integer|min:1|max:100',
            'completion_criteria.activities' => 'required_if:completion_criteria.type,specific_activities|nullable|array',
            'completion_criteria.activities.*' => 'integer|exists:activities,id',
            'expiry_period' => 'nullable|integer',
            'allow_download' => 'boolean',
            'download_formats' => 'required_if:allow_download,true|array',
            'download_formats.*' => 'string|in:pdf,jpg,png',
            'allow_sharing' => 'boolean',
            'sharing_platforms' => 'required_if:allow_sharing,true|array',
            'sharing_platforms.*' => 'string|in:linkedin,facebook,twitter,email',
            'verification_enabled' => 'boolean',
            'verification_method' => 'required_if:verification_enabled,true|string|in:qr,link,code',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Prepare data for update
        $updateData = $request->except(['custom_fields', 'completion_criteria', 'download_formats', 'sharing_platforms']);
        
        // Handle arrays that need to be stored as JSON
        if ($request->has('custom_fields')) {
            $updateData['custom_fields'] = json_encode($request->custom_fields);
        }
        
        if ($request->has('completion_criteria')) {
            $updateData['completion_criteria'] = json_encode($request->completion_criteria);
        }
        
        if ($request->has('download_formats')) {
            $updateData['download_formats'] = json_encode($request->download_formats);
        }
        
        if ($request->has('sharing_platforms')) {
            $updateData['sharing_platforms'] = json_encode($request->sharing_platforms);
        }

        $certificateContent->update($updateData);

        // Decode JSON fields for the response
        if ($certificateContent->custom_fields) {
            $certificateContent->custom_fields = json_decode($certificateContent->custom_fields);
        }
        
        if ($certificateContent->completion_criteria) {
            $certificateContent->completion_criteria = json_decode($certificateContent->completion_criteria);
        }
        
        if ($certificateContent->download_formats) {
            $certificateContent->download_formats = json_decode($certificateContent->download_formats);
        }
        
        if ($certificateContent->sharing_platforms) {
            $certificateContent->sharing_platforms = json_decode($certificateContent->sharing_platforms);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Certificate content updated successfully',
            'data' => $certificateContent,
        ]);
    }

    /**
     * Remove the specified certificate content from storage.
     *
     * @param  int  $activityId
     * @return \Illuminate\Http\Response
     * 
     * @OA\Delete(
     *     path="/activities/{activityId}/certificate-content",
     *     summary="Delete certificate content for an activity",
     *     description="Deletes certificate content for a certificate-type activity",
     *     operationId="deleteCertificateContent",
     *     tags={"Content Types"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="activityId",
     *         in="path",
     *         required=true,
     *         description="ID of the activity",
     *         @OA\Schema(type="integer", format="int64")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Certificate content deleted successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Certificate content deleted successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - User does not have permission"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Activity not found"
     *     )
     * )
     */
    public function destroy($activityId)
    {
        $activity = Activity::findOrFail($activityId);
        $block = Block::findOrFail($activity->block_id);
        $template = Template::findOrFail($block->template_id);

        // Check if user has permission to delete this content
        if ($template->created_by !== Auth::id()) {
            return response()->json([
                'status' => 'error',
                'message' => 'You do not have permission to delete this content',
            ], Response::HTTP_FORBIDDEN);
        }

        $certificateContent = CertificateContent::where('activity_id', $activityId)->firstOrFail();
        $certificateContent->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Certificate content deleted successfully',
        ]);
    }
}
