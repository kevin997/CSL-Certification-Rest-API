<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Environment;
use App\Models\EnvironmentUser;
use App\Models\Product;
use App\Models\ProductLandingPage;
use App\Scopes\EnvironmentScope;
use App\Support\Tenancy\EnvironmentResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class ProductLandingPageController extends Controller
{
    /**
     * Environment membership roles that may manage a product's marketing page.
     *
     * Learners are environment members too (`learner`, `company_learner`), so
     * membership alone is not authorisation.
     *
     * @var list<string>
     */
    private const MANAGING_ROLES = ['owner', 'company_team_member'];

    /**
     * Platform-wide roles that may manage any product's page.
     *
     * @var list<string>
     */
    private const PLATFORM_ROLES = ['super_admin', 'admin'];

    /**
     * The product this request is allowed to manage, or the response to send.
     *
     * EnvironmentScope is deliberately not relied on for this. It applies only
     * when the session carries a current_environment_id -- a bearer-token
     * client on a host DetectEnvironment cannot resolve gets no filtering at
     * all -- and even when it does apply it matches rows whose environment_id
     * is null. Membership is therefore established here rather than inferred
     * from a query scope.
     *
     * A product outside the caller's environments is reported as missing, so
     * this does not confirm which products another tenant owns. Insufficient
     * role inside their own environment is a 403, which is the honest answer.
     */
    private function manageableProduct(int $id): Product|JsonResponse
    {
        $user = Auth::user();

        $missing = response()->json([
            'status' => 'error',
            'message' => 'Product not found',
        ], Response::HTTP_NOT_FOUND);

        if (! $user) {
            return $missing;
        }

        $product = Product::withoutGlobalScope(EnvironmentScope::class)->find($id);

        // A product belonging to no environment has no membership to check
        // against, so nobody but platform staff can manage its page.
        if (! $product) {
            return $missing;
        }

        // Null-safe: role is nullable on users, and a user without one is not
        // platform staff rather than a 500.
        if (in_array($user->role?->value, self::PLATFORM_ROLES, true)) {
            return $product;
        }

        if (! $product->environment_id) {
            return $missing;
        }

        $environment = Environment::withoutGlobalScope(EnvironmentScope::class)
            ->find($product->environment_id);

        if ($environment && (int) $environment->owner_id === (int) $user->id) {
            return $product;
        }

        $membership = EnvironmentUser::where('environment_id', $product->environment_id)
            ->where('user_id', $user->id)
            ->first();

        if (! $membership) {
            return $missing;
        }

        if (! in_array($membership->role, self::MANAGING_ROLES, true)) {
            return response()->json([
                'status' => 'error',
                'message' => 'You do not have permission to manage this product.',
            ], Response::HTTP_FORBIDDEN);
        }

        return $product;
    }

    /**
     * The page for a product, or an empty one.
     *
     * Deliberately does not filter on `enabled`: the editor has to be able to
     * open a page that is switched off. Only the public endpoint enforces it.
     */
    public function show(int $id): JsonResponse
    {
        $product = $this->manageableProduct($id);

        if ($product instanceof JsonResponse) {
            return $product;
        }

        $page = ProductLandingPage::where('product_id', $product->id)->first();

        // A read must not write. An untouched product reports an empty page
        // rather than having one created for it.
        return response()->json([
            'status' => 'success',
            'data' => [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'page_data' => $page?->page_data,
                'seo_title' => $page?->seo_title,
                'seo_description' => $page?->seo_description,
                'enabled' => (bool) $page?->enabled,
            ],
        ]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $product = $this->manageableProduct($id);

        if ($product instanceof JsonResponse) {
            return $product;
        }

        $validator = Validator::make($request->all(), [
            'page_data' => 'nullable|array',
            'seo_title' => 'nullable|string|max:191',
            'seo_description' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Only the keys the client actually sent. The editor saves the built
        // page and the SEO fields through separate requests, so writing every
        // column unconditionally made each save null the other one's work.
        $attributes = $validator->validated();
        $attributes['environment_id'] = $product->environment_id;

        $page = ProductLandingPage::updateOrCreate(
            ['product_id' => $product->id],
            $attributes
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Landing page saved',
            'data' => $page,
        ]);
    }

    public function toggle(Request $request, int $id): JsonResponse
    {
        $product = $this->manageableProduct($id);

        if ($product instanceof JsonResponse) {
            return $product;
        }

        $validator = Validator::make($request->all(), [
            'enabled' => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $page = ProductLandingPage::updateOrCreate(
            ['product_id' => $product->id],
            [
                'environment_id' => $product->environment_id,
                'enabled' => $request->boolean('enabled'),
            ]
        );

        return response()->json([
            'status' => 'success',
            'data' => $page,
        ]);
    }

    /**
     * The published page for a product, resolved by storefront identifier.
     *
     * Public requests carry no session, so EnvironmentScope -- which reads
     * session('current_environment_id') -- would match nothing. The scope is
     * dropped and the environment filtered explicitly, as
     * BrandingController::getPublicLandingPage does.
     */
    public function publicShow(Request $request): JsonResponse
    {
        $environment = $this->resolveEnvironment($request);

        if (! $environment) {
            return response()->json([
                'status' => 'error',
                'message' => 'Environment not found for domain',
            ], Response::HTTP_NOT_FOUND);
        }

        $product = Product::withoutGlobalScope(EnvironmentScope::class)
            ->where('environment_id', $environment->id)
            ->where('slug', (string) $request->query('slug'))
            ->first();

        if (! $product) {
            return response()->json([
                'status' => 'error',
                'message' => 'Product not found',
            ], Response::HTTP_NOT_FOUND);
        }

        // enabled and page_data move independently -- the switch can be turned
        // on before anything has been built -- and a row with no page_data
        // renders as an error, which is exactly what the redirect exists to
        // avoid. Treat it as unpublished.
        $page = ProductLandingPage::withoutGlobalScope(EnvironmentScope::class)
            ->where('environment_id', $environment->id)
            ->where('product_id', $product->id)
            ->where('enabled', true)
            ->whereNotNull('page_data')
            ->first();

        if (! $page) {
            return response()->json([
                'status' => 'error',
                'message' => 'Landing page not published',
            ], Response::HTTP_NOT_FOUND);
        }

        // Only what the page renders. The product row is not public.
        return response()->json([
            'status' => 'success',
            'data' => [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'page_data' => $page->page_data,
                'seo_title' => $page->seo_title,
                'seo_description' => $page->seo_description,
            ],
        ]);
    }

    /**
     * Resolve the tenant for a public request.
     *
     * The storefront passes the `{domain}` segment of its own URL, which is the
     * identifier the sibling catalog route resolves with: a numeric id, a
     * primary domain, a subdomain or an additional domain. Resolving from the
     * request Host instead only ever matched a custom primary domain, so on a
     * subdomain or the shared host the page redirected forever.
     *
     * The Host is kept as a fallback for callers that send no identifier.
     */
    private function resolveEnvironment(Request $request): ?Environment
    {
        $identifier = trim((string) $request->query('domain'));

        if ($identifier !== '') {
            return Environment::resolveByIdentifier($identifier);
        }

        return app(EnvironmentResolver::class)->resolve($request)->environment;
    }
}
