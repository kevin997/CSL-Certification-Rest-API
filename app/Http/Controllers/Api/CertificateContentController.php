<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Block;
use App\Models\CertificateContent;
use App\Models\CertificateTemplate;
use App\Models\Template;
use App\Services\CertificateGenerationService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

/**
 * @OA\Schema(
 *     schema="CertificateContent",
 *     required={"activity_id", "title"},
 *
 *     description="Mirrors the certificate_contents table. Signatory, branding
 *     and sharing options are properties of the uploaded PDF template, not of
 *     this record, and have no column here.",
 *
 *     @OA\Property(property="id", type="integer", format="int64", example=1),
 *     @OA\Property(property="activity_id", type="integer", format="int64", example=382),
 *     @OA\Property(property="title", type="string", example="Certificate of Completion"),
 *     @OA\Property(property="description", type="string", nullable=true, example="Certificate for course completion"),
 *     @OA\Property(property="template_path", type="string", nullable=true, example="templates/standard.pdf"),
 *     @OA\Property(property="certificate_template_id", type="integer", format="int64", nullable=true, example=7),
 *     @OA\Property(property="fields_config", type="object", nullable=true),
 *     @OA\Property(property="completion_criteria", type="object", nullable=true),
 *     @OA\Property(property="auto_issue", type="boolean", example=true),
 *     @OA\Property(property="expiry_period", type="integer", nullable=true, example=3),
 *     @OA\Property(property="expiry_period_unit", type="string", enum={"days", "months", "years"}, example="years"),
 *     @OA\Property(property="metadata", type="object", nullable=true),
 *     @OA\Property(property="created_by", type="integer", format="int64", example=22),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time"),
 *     @OA\Property(property="deleted_at", type="string", format="date-time", nullable=true)
 * )
 */
class CertificateContentController extends Controller
{
    /**
     * Certificate generation service
     *
     * @var CertificateGenerationService
     */
    protected $certificateGenerationService;

    /**
     * Constructor
     */
    public function __construct(CertificateGenerationService $certificateGenerationService)
    {
        $this->certificateGenerationService = $certificateGenerationService;
    }

    /**
     * Store a newly created certificate content in storage.
     *
     * @param  int  $activityId
     * @return Response
     *
     * @OA\Post(
     *     path="/activities/{activityId}/certificate-content",
     *     summary="Create certificate content for an activity",
     *     description="Creates new certificate content for a certificate-type activity",
     *     operationId="storeCertificateContent",
     *     tags={"Content Types"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="activityId",
     *         in="path",
     *         required=true,
     *         description="ID of the activity",
     *
     *         @OA\Schema(type="integer", format="int64")
     *     ),
     *
     *     @OA\RequestBody(
     *         required=true,
     *         description="Certificate content data",
     *
     *         @OA\JsonContent(
     *             required={"title", "description", "template_type"},
     *
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
     *
     *                 @OA\Items(
     *                     type="object",
     *
     *                     @OA\Property(property="name", type="string", example="Course Duration"),
     *                     @OA\Property(property="value", type="string", example="120 Hours"),
     *                     @OA\Property(property="position", type="string", enum={"header", "body", "footer"}, example="body")
     *                 )
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=201,
     *         description="Certificate content created successfully",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Certificate content created successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/CertificateContent")
     *         )
     *     ),
     *
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
            // Only columns that exist on certificate_contents are validated.
            // certificate_type, template_design, signatory_*, custom_fields,
            // download_formats, sharing_platforms, allow_*, verification_* and
            // the *_url fields have no column and no reader anywhere in the
            // app: $fillable dropped them on save and certificate generation
            // takes its values from the PDF template's own declared fields.
            // Requiring them rejected valid requests for data the API then
            // discarded. Persisting them would need a schema change and a
            // generation change, not a validation rule.
            'certificate_template_id' => 'nullable|exists:certificate_templates,id',
            'completion_criteria' => 'required|array',
            'completion_criteria.type' => 'required|string|in:all_activities,percentage,specific_activities',
            'completion_criteria.value' => 'required_if:completion_criteria.type,percentage|nullable|integer|min:1|max:100',
            'completion_criteria.activities' => 'required_if:completion_criteria.type,specific_activities|nullable|array',
            'completion_criteria.activities.*' => 'integer|exists:activities,id',
            'expiry_period' => 'nullable|integer', // number of time units, null means never expires
            'expiry_period_unit' => 'required_with:expiry_period|string|in:days,months,years',
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

        // If certificate_template_id is provided, fetch the template path from the certificate template
        if ($request->has('certificate_template_id') && $request->certificate_template_id) {
            // Scoped: the id comes from the request, so an unscoped lookup let a
            // tenant attach another tenant's template to its own content.
            $certificateTemplate = CertificateTemplate::query()
                ->forEnvironment(session('current_environment_id'))
                ->findOrFail($request->certificate_template_id);
            $data['template_path'] = $certificateTemplate->file_path;
        }

        // Handle arrays that need to be stored as JSON
        if ($request->has('completion_criteria')) {
            $data['completion_criteria'] = json_encode($request->completion_criteria);
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
     * @return Response
     *
     * @OA\Get(
     *     path="/activities/{activityId}/certificate-content",
     *     summary="Get certificate content for an activity",
     *     description="Retrieves certificate content for a certificate-type activity",
     *     operationId="getCertificateContent",
     *     tags={"Content Types"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="activityId",
     *         in="path",
     *         required=true,
     *         description="ID of the activity",
     *
     *         @OA\Schema(type="integer", format="int64")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Certificate content retrieved successfully",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="data", ref="#/components/schemas/CertificateContent")
     *         )
     *     ),
     *
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
        if (! $template->is_public && $template->created_by !== Auth::id()) {
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

        // Add certificate preview and download URLs if metadata exists
        $responseData = $certificateContent->toArray();

        // Add preview and download URLs to the response if certificate has been generated
        if ($certificateContent->metadata && isset($certificateContent->metadata['certificate_url'])) {
            $responseData['preview_url'] = $this->certificateGenerationService->getCertificatePreviewUrl($certificateContent);
            $responseData['download_url'] = $this->certificateGenerationService->getCertificateDownloadUrl($certificateContent);
        }

        return response()->json([
            'status' => 'success',
            'data' => $responseData,
        ]);
    }

    /**
     * Update the specified certificate content in storage.
     *
     * @param  int  $activityId
     * @return Response
     *
     * @OA\Put(
     *     path="/activities/{activityId}/certificate-content",
     *     summary="Update certificate content for an activity",
     *     description="Updates certificate content for a certificate-type activity",
     *     operationId="updateCertificateContent",
     *     tags={"Content Types"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="activityId",
     *         in="path",
     *         required=true,
     *         description="ID of the activity",
     *
     *         @OA\Schema(type="integer", format="int64")
     *     ),
     *
     *     @OA\RequestBody(
     *         required=true,
     *         description="Certificate content data",
     *
     *         @OA\JsonContent(
     *
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
     *
     *                 @OA\Items(
     *                     type="object",
     *
     *                     @OA\Property(property="name", type="string", example="Course Duration"),
     *                     @OA\Property(property="value", type="string", example="120 Hours"),
     *                     @OA\Property(property="position", type="string", enum={"header", "body", "footer"}, example="body")
     *                 )
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Certificate content updated successfully",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Certificate content updated successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/CertificateContent")
     *         )
     *     ),
     *
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
            // Only columns that exist on certificate_contents are validated.
            // certificate_type, template_design, signatory_*, custom_fields,
            // download_formats, sharing_platforms, allow_*, verification_* and
            // the *_url fields have no column and no reader anywhere in the
            // app: $fillable dropped them on save and certificate generation
            // takes its values from the PDF template's own declared fields.
            // Requiring them rejected valid requests for data the API then
            // discarded. Persisting them would need a schema change and a
            // generation change, not a validation rule.
            'completion_criteria' => 'array',
            'completion_criteria.type' => 'string|in:all_activities,percentage,specific_activities',
            'completion_criteria.value' => 'required_if:completion_criteria.type,percentage|nullable|integer|min:1|max:100',
            'completion_criteria.activities' => 'required_if:completion_criteria.type,specific_activities|nullable|array',
            'completion_criteria.activities.*' => 'integer|exists:activities,id',
            'expiry_period' => 'nullable|integer',
            'expiry_period_unit' => 'required_with:expiry_period|string|in:days,months,years',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Prepare data for update
        $updateData = $request->except(['custom_fields', 'completion_criteria', 'download_formats', 'sharing_platforms']);

        // If certificate_template_id is provided, fetch the template path from the certificate template
        if ($request->has('certificate_template_id') && $request->certificate_template_id) {
            // Only update template_path if the template ID has changed
            if ($certificateContent->certificate_template_id != $request->certificate_template_id) {
                // Scoped, as in store(): a request-supplied id must not reach
                // another tenant's template.
                $certificateTemplate = CertificateTemplate::query()
                    ->forEnvironment(session('current_environment_id'))
                    ->findOrFail($request->certificate_template_id);
                $updateData['template_path'] = $certificateTemplate->file_path;
            }
        }

        // Handle arrays that need to be stored as JSON
        if ($request->has('completion_criteria')) {
            $updateData['completion_criteria'] = json_encode($request->completion_criteria);
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
     * @return Response
     *
     * @OA\Delete(
     *     path="/activities/{activityId}/certificate-content",
     *     summary="Delete certificate content for an activity",
     *     description="Deletes certificate content for a certificate-type activity",
     *     operationId="deleteCertificateContent",
     *     tags={"Content Types"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="activityId",
     *         in="path",
     *         required=true,
     *         description="ID of the activity",
     *
     *         @OA\Schema(type="integer", format="int64")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Certificate content deleted successfully",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Certificate content deleted successfully")
     *         )
     *     ),
     *
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
