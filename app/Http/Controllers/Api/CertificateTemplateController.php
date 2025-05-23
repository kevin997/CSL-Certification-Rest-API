<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CertificateTemplate;
use App\Services\CertificateGenerationService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class CertificateTemplateController extends Controller
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
     * List available certificate templates
     * 
     * @return Response
     * 
     * @OA\Get(
     *     path="/certificate-templates",
     *     summary="List available certificate templates",
     *     description="Returns a list of available certificate templates",
     *     operationId="listCertificateTemplates",
     *     tags={"Certificates"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="List of templates",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/CertificateTemplate"))
     *         )
     *     )
     * )
     */
    public function index()
    {
        $templates = CertificateTemplate::all();
        
        return response()->json([
            'status' => 'success',
            'data' => $templates,
        ]);
    }

    /**
     * Upload a new certificate template
     * 
     * @param Request $request
     * @return Response
     * 
     * @OA\Post(
     *     path="/certificate-templates",
     *     summary="Upload a new certificate template",
     *     description="Uploads a new certificate template to the system",
     *     operationId="uploadCertificateTemplate",
     *     tags={"Certificates"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"template", "name"},
     *                 @OA\Property(property="template", type="string", format="binary", description="PDF template file"),
     *                 @OA\Property(property="name", type="string", description="Template name"),
     *                 @OA\Property(property="description", type="string", description="Template description"),
     *                 @OA\Property(property="template_type", type="string", enum={"completion", "achievement", "participation", "custom"}, description="Template type")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Template uploaded successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Template uploaded successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/CertificateTemplate")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     )
     * )
     */
    public function store(Request $request)
    {
        // Validate request
        $validator = Validator::make($request->all(), [
            'template' => 'required|file|mimes:pdf|max:5120', // Max 5MB PDF file
            'name' => 'required|string|max:100',
            'description' => 'nullable|string',
            'template_type' => 'required|string|in:completion,achievement,participation,custom',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Upload template to certificate service
        $result = $this->certificateGenerationService->uploadTemplate(
            $request->file('template'),
            $request->name
        );

        if (!$result) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to upload template to certificate service',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        // Log the response from certificate service for debugging
        Log::info('Certificate service response', ['result' => $result]);
        
        // Extract data from the nested response structure
        $templateData = $result['data'] ?? $result;
        
        // Create template record in database with correct data structure
        $template = CertificateTemplate::create([
            'name' => $request->name,
            'description' => $request->description,
            'filename' => $templateData['filename'] ?? $request->name . '.pdf',
            'file_path' => $templateData['path'] ?? null,
            'template_type' => $request->template_type,
            'is_default' => false,
            'created_by' => Auth::id(),
            'remote_id' => $templateData['id'] ?? null,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Template uploaded successfully',
            'data' => $template,
        ]);
    }

    /**
     * Get a specific certificate template
     * 
     * @param int $id
     * @return Response
     * 
     * @OA\Get(
     *     path="/certificate-templates/{id}",
     *     summary="Get a specific certificate template",
     *     description="Returns details of a specific certificate template",
     *     operationId="getCertificateTemplate",
     *     tags={"Certificates"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the template",
     *         @OA\Schema(type="integer", format="int64")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Template details",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="data", ref="#/components/schemas/CertificateTemplate")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Template not found"
     *     )
     * )
     */
    public function show($id)
    {
        $template = CertificateTemplate::findOrFail($id);
        
        return response()->json([
            'status' => 'success',
            'data' => $template,
        ]);
    }

    /**
     * Set a template as default for its type
     * 
     * @param int $id
     * @return Response
     * 
     * @OA\Put(
     *     path="/certificate-templates/{id}/set-default",
     *     summary="Set a template as default for its type",
     *     description="Sets a template as the default for its type",
     *     operationId="setDefaultCertificateTemplate",
     *     tags={"Certificates"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the template to set as default",
     *         @OA\Schema(type="integer", format="int64")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Template set as default successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Template set as default successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Template not found"
     *     )
     * )
     */
    public function setDefault($id)
    {
        $template = CertificateTemplate::findOrFail($id);
        
        // Reset all templates of this type to non-default
        CertificateTemplate::where('template_type', $template->template_type)
            ->update(['is_default' => false]);
        
        // Set this template as default
        $template->is_default = true;
        $template->save();
        
        return response()->json([
            'status' => 'success',
            'message' => 'Template set as default successfully',
        ]);
    }

    /**
     * Delete a certificate template
     * 
     * @param int $id
     * @return Response
     * 
     * @OA\Delete(
     *     path="/certificate-templates/{id}",
     *     summary="Delete a certificate template",
     *     description="Deletes a certificate template from the system",
     *     operationId="deleteCertificateTemplate",
     *     tags={"Certificates"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the template to delete",
     *         @OA\Schema(type="integer", format="int64")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Template deleted successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Template deleted successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Template not found"
     *     )
     * )
     */
    public function destroy($id)
    {
        // Find template
        $template = CertificateTemplate::findOrFail($id);

        // Delete from certificate service
        $result = $this->certificateGenerationService->deleteTemplate($template->filename);

        if (!$result) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete template from certificate service',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        // Delete from database
        $template->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Template deleted successfully',
        ]);
    }
}
