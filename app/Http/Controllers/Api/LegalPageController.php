<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LegalPage;
use App\Services\LegalPageService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

/**
 * @OA\Schema(
 *     schema="LegalPage",
 *     required={"environment_id", "user_id", "page_type"},
 *     @OA\Property(property="id", type="integer", format="int64", example=1),
 *     @OA\Property(property="environment_id", type="integer", format="int64", example=1),
 *     @OA\Property(property="user_id", type="integer", format="int64", example=1),
 *     @OA\Property(property="page_type", type="string", example="privacy_policy"),
 *     @OA\Property(property="title", type="string", example="Privacy Policy", nullable=true),
 *     @OA\Property(property="content", type="string", example="<p>Your privacy is important to us...</p>", nullable=true),
 *     @OA\Property(property="seo_title", type="string", example="Privacy Policy - Company Name", nullable=true),
 *     @OA\Property(property="seo_description", type="string", example="Learn about how we protect your privacy", nullable=true),
 *     @OA\Property(property="is_published", type="boolean", example=true),
 *     @OA\Property(property="published_at", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 */
class LegalPageController extends Controller
{
    protected LegalPageService $legalPageService;

    public function __construct(LegalPageService $legalPageService)
    {
        $this->legalPageService = $legalPageService;
    }

    /**
     * Get all page types with their status for the current environment.
     *
     * @OA\Get(
     *     path="/legal-pages",
     *     summary="Get all legal page types with status",
     *     description="Returns all available legal page types with their current status",
     *     operationId="getLegalPageTypes",
     *     tags={"Legal Pages"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="page_types",
     *                     type="array",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="type", type="string", example="privacy_policy"),
     *                         @OA\Property(property="name", type="string", example="Privacy Policy"),
     *                         @OA\Property(property="description", type="string"),
     *                         @OA\Property(property="is_set", type="boolean"),
     *                         @OA\Property(property="is_published", type="boolean"),
     *                         @OA\Property(property="page_id", type="integer", nullable=true)
     *                     )
     *                 ),
     *                 @OA\Property(
     *                     property="dynamic_tags",
     *                     type="array",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="name", type="string"),
     *                         @OA\Property(property="tag", type="string")
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function index(Request $request)
    {
        $environmentId = $request->input('environment_id') ?? session('current_environment_id');

        if (!$environmentId) {
            return response()->json([
                'status' => 'error',
                'message' => 'Environment ID is required',
            ], Response::HTTP_BAD_REQUEST);
        }

        $pageTypes = $this->legalPageService->getPageTypesWithStatus($environmentId);
        $dynamicTags = $this->legalPageService->getDynamicTags();

        return response()->json([
            'status' => 'success',
            'data' => [
                'page_types' => $pageTypes,
                'dynamic_tags' => $dynamicTags,
            ],
        ]);
    }

    /**
     * Get a specific legal page by ID.
     *
     * @OA\Get(
     *     path="/legal-pages/{id}",
     *     summary="Get a legal page by ID",
     *     description="Returns a specific legal page",
     *     operationId="getLegalPageById",
     *     tags={"Legal Pages"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="data", ref="#/components/schemas/LegalPage")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Page not found")
     * )
     */
    public function show(int $id)
    {
        $page = $this->legalPageService->getPageById($id);

        if (!$page) {
            return response()->json([
                'status' => 'error',
                'message' => 'Legal page not found',
            ], Response::HTTP_NOT_FOUND);
        }

        return response()->json([
            'status' => 'success',
            'data' => $page,
        ]);
    }

    /**
     * Get a legal page by type.
     *
     * @OA\Get(
     *     path="/legal-pages/type/{pageType}",
     *     summary="Get a legal page by type",
     *     description="Returns a legal page by its type",
     *     operationId="getLegalPageByType",
     *     tags={"Legal Pages"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="pageType",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="string", enum={"about_us", "privacy_policy", "legal_notice", "terms_of_service"})
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="data", ref="#/components/schemas/LegalPage")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Page not found")
     * )
     */
    public function showByType(Request $request, string $pageType)
    {
        if (!LegalPage::isValidPageType($pageType)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid page type',
            ], Response::HTTP_BAD_REQUEST);
        }

        $environmentId = $request->input('environment_id') ?? session('current_environment_id');

        if (!$environmentId) {
            return response()->json([
                'status' => 'error',
                'message' => 'Environment ID is required',
            ], Response::HTTP_BAD_REQUEST);
        }

        $page = $this->legalPageService->getPageByType($environmentId, $pageType);

        // Return empty page structure if not found (for editor)
        if (!$page) {
            return response()->json([
                'status' => 'success',
                'data' => [
                    'id' => null,
                    'page_type' => $pageType,
                    'title' => LegalPage::PAGE_TYPES[$pageType] ?? $pageType,
                    'content' => null,
                    'seo_title' => null,
                    'seo_description' => null,
                    'is_published' => false,
                    'published_at' => null,
                    'page_type_name' => LegalPage::PAGE_TYPES[$pageType] ?? $pageType,
                    'page_type_description' => LegalPage::PAGE_DESCRIPTIONS[$pageType] ?? '',
                ],
            ]);
        }

        return response()->json([
            'status' => 'success',
            'data' => array_merge($page->toArray(), [
                'page_type_name' => $page->page_type_name,
                'page_type_description' => $page->page_type_description,
            ]),
        ]);
    }

    /**
     * Create or update a legal page.
     *
     * @OA\Post(
     *     path="/legal-pages",
     *     summary="Create or update a legal page",
     *     description="Creates a new legal page or updates an existing one",
     *     operationId="storeLegalPage",
     *     tags={"Legal Pages"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"page_type"},
     *             @OA\Property(property="page_type", type="string", enum={"about_us", "privacy_policy", "legal_notice", "terms_of_service"}),
     *             @OA\Property(property="title", type="string"),
     *             @OA\Property(property="content", type="string"),
     *             @OA\Property(property="seo_title", type="string"),
     *             @OA\Property(property="seo_description", type="string"),
     *             @OA\Property(property="is_published", type="boolean")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Page saved successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="data", ref="#/components/schemas/LegalPage")
     *         )
     *     ),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), $this->legalPageService->getValidationRules());

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $environmentId = $request->input('environment_id') ?? session('current_environment_id');

        if (!$environmentId) {
            return response()->json([
                'status' => 'error',
                'message' => 'Environment ID is required',
            ], Response::HTTP_BAD_REQUEST);
        }

        $data = $request->all();
        $data['environment_id'] = $environmentId;
        $data['user_id'] = Auth::id();

        $page = $this->legalPageService->createOrUpdatePage($data);

        return response()->json([
            'status' => 'success',
            'message' => 'Legal page saved successfully',
            'data' => $page,
        ]);
    }

    /**
     * Update a legal page.
     *
     * @OA\Put(
     *     path="/legal-pages/{id}",
     *     summary="Update a legal page",
     *     description="Updates an existing legal page",
     *     operationId="updateLegalPage",
     *     tags={"Legal Pages"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="title", type="string"),
     *             @OA\Property(property="content", type="string"),
     *             @OA\Property(property="seo_title", type="string"),
     *             @OA\Property(property="seo_description", type="string"),
     *             @OA\Property(property="is_published", type="boolean")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Page updated successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="data", ref="#/components/schemas/LegalPage")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Page not found")
     * )
     */
    public function update(Request $request, int $id)
    {
        $page = $this->legalPageService->getPageById($id);

        if (!$page) {
            return response()->json([
                'status' => 'error',
                'message' => 'Legal page not found',
            ], Response::HTTP_NOT_FOUND);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'nullable|string|max:255',
            'content' => 'nullable|string',
            'seo_title' => 'nullable|string|max:255',
            'seo_description' => 'nullable|string|max:500',
            'is_published' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $updatedPage = $this->legalPageService->updatePage($id, $request->all());

        return response()->json([
            'status' => 'success',
            'message' => 'Legal page updated successfully',
            'data' => $updatedPage,
        ]);
    }

    /**
     * Publish a legal page.
     *
     * @OA\Post(
     *     path="/legal-pages/{id}/publish",
     *     summary="Publish a legal page",
     *     description="Publishes a legal page",
     *     operationId="publishLegalPage",
     *     tags={"Legal Pages"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Page published successfully"
     *     ),
     *     @OA\Response(response=404, description="Page not found")
     * )
     */
    public function publish(int $id)
    {
        try {
            $page = $this->legalPageService->publishPage($id);

            return response()->json([
                'status' => 'success',
                'message' => 'Legal page published successfully',
                'data' => $page,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], Response::HTTP_NOT_FOUND);
        }
    }

    /**
     * Unpublish a legal page.
     *
     * @OA\Post(
     *     path="/legal-pages/{id}/unpublish",
     *     summary="Unpublish a legal page",
     *     description="Unpublishes a legal page",
     *     operationId="unpublishLegalPage",
     *     tags={"Legal Pages"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Page unpublished successfully"
     *     ),
     *     @OA\Response(response=404, description="Page not found")
     * )
     */
    public function unpublish(int $id)
    {
        try {
            $page = $this->legalPageService->unpublishPage($id);

            return response()->json([
                'status' => 'success',
                'message' => 'Legal page unpublished successfully',
                'data' => $page,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], Response::HTTP_NOT_FOUND);
        }
    }

    /**
     * Delete a legal page.
     *
     * @OA\Delete(
     *     path="/legal-pages/{id}",
     *     summary="Delete a legal page",
     *     description="Deletes a legal page",
     *     operationId="deleteLegalPage",
     *     tags={"Legal Pages"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Page deleted successfully"
     *     ),
     *     @OA\Response(response=404, description="Page not found")
     * )
     */
    public function destroy(int $id)
    {
        $deleted = $this->legalPageService->deletePage($id);

        if (!$deleted) {
            return response()->json([
                'status' => 'error',
                'message' => 'Legal page not found',
            ], Response::HTTP_NOT_FOUND);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Legal page deleted successfully',
        ]);
    }

    /**
     * Get a public legal page by type (no authentication required).
     *
     * @OA\Get(
     *     path="/legal-pages/public/{pageType}",
     *     summary="Get a public legal page",
     *     description="Returns a published legal page for public viewing",
     *     operationId="getPublicLegalPage",
     *     tags={"Legal Pages"},
     *     @OA\Parameter(
     *         name="pageType",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="string", enum={"about_us", "privacy_policy", "legal_notice", "terms_of_service"})
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="title", type="string"),
     *                 @OA\Property(property="content", type="string"),
     *                 @OA\Property(property="seo_title", type="string"),
     *                 @OA\Property(property="seo_description", type="string"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=404, description="Page not found or not published")
     * )
     */
    public function getPublicPage(Request $request, string $pageType)
    {
        if (!LegalPage::isValidPageType($pageType)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid page type',
            ], Response::HTTP_BAD_REQUEST);
        }

        // Detect environment from domain
        $environmentId = $this->detectEnvironmentFromRequest($request);

        if (!$environmentId) {
            return response()->json([
                'status' => 'error',
                'message' => 'Could not determine environment',
            ], Response::HTTP_BAD_REQUEST);
        }

        $page = $this->legalPageService->getPublishedPageByType($environmentId, $pageType);

        if (!$page) {
            return response()->json([
                'status' => 'error',
                'message' => 'Page not found or not published',
            ], Response::HTTP_NOT_FOUND);
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'title' => $page->title,
                'content' => $page->processed_content,
                'seo_title' => $page->seo_title,
                'seo_description' => $page->seo_description,
                'updated_at' => $page->updated_at?->toISOString(),
            ],
        ]);
    }

    /**
     * Detect environment ID from request headers/domain.
     */
    private function detectEnvironmentFromRequest(Request $request): ?int
    {
        $domain = null;

        // Try to get domain from headers
        $frontendDomainHeader = $request->header('X-Frontend-Domain');
        $origin = $request->header('Origin');
        $referer = $request->header('Referer');

        if ($frontendDomainHeader) {
            $domain = $frontendDomainHeader;
        } elseif ($origin) {
            $parsedOrigin = parse_url($origin);
            $domain = $parsedOrigin['host'] ?? null;
        } elseif ($referer) {
            $parsedReferer = parse_url($referer);
            $domain = $parsedReferer['host'] ?? null;
        }

        if (!$domain) {
            $domain = $request->query('domain') ?: $request->getHost();
        }

        // Find environment by domain
        $environment = \App\Models\Environment::where('primary_domain', $domain)
            ->orWhere(function ($query) use ($domain) {
                $query->whereNotNull('additional_domains')
                    ->whereJsonContains('additional_domains', $domain);
            })
            ->where('is_active', true)
            ->first();

        return $environment?->id;
    }
}
