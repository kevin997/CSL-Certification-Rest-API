<?php

namespace App\Http\Controllers\Api\Sales;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Product;
use App\Models\SalesForm;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SalesFormController extends Controller
{
    /**
     * List sales forms for the current environment.
     *
     * GET /api/sales-forms
     */
    public function index(Request $request)
    {
        $query = SalesForm::query()->withCount(['fields', 'products', 'submissions']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $query->where('title', 'like', '%' . $request->search . '%');
        }

        $query->orderBy($request->get('sort_field', 'created_at'), $request->get('sort_direction', 'desc'));

        $forms = $query->paginate($request->get('per_page', 10));

        return response()->json([
            'success' => true,
            'data' => $forms->items(),
            'meta' => [
                'current_page' => $forms->currentPage(),
                'last_page' => $forms->lastPage(),
                'per_page' => $forms->perPage(),
                'total' => $forms->total(),
            ],
        ]);
    }

    /**
     * Show a single sales form with its fields, products and access blocks.
     *
     * GET /api/sales-forms/{id}
     */
    public function show($id)
    {
        $form = SalesForm::with(['fields', 'products', 'accessBlocks'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $form,
            'public_url' => $form->public_url,
        ]);
    }

    /**
     * Create a sales form.
     *
     * POST /api/sales-forms
     */
    public function store(Request $request)
    {
        $this->normalizeSlugInput($request);
        $validator = $this->validator($request);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            DB::beginTransaction();

            $form = SalesForm::create([
                'title' => $request->title,
                'description' => $request->description,
                'slug' => $request->filled('slug')
                    ? SalesForm::generateUniqueSlug($request->slug)
                    : SalesForm::generateUniqueSlug($request->title),
                'status' => $request->get('status', SalesForm::STATUS_DRAFT),
                'cover_image_path' => $request->cover_image_path,
                'youtube_url' => $request->youtube_url,
                'settings' => $request->settings,
                'created_by' => Auth::id(),
            ]);

            $this->syncFields($form, $request->input('fields', []));
            $this->syncProducts($form, $request->input('product_ids', []));
            $this->syncAccessBlocks($form, $request->input('access_blocks', []));

            DB::commit();

            $form->load(['fields', 'products', 'accessBlocks']);

            return response()->json([
                'success' => true,
                'message' => 'Sales form created successfully',
                'data' => $form,
                'public_url' => $form->public_url,
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to create sales form: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update a sales form.
     *
     * PUT /api/sales-forms/{id}
     */
    public function update(Request $request, $id)
    {
        $form = SalesForm::findOrFail($id);

        $this->normalizeSlugInput($request);
        $validator = $this->validator($request, $form);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            DB::beginTransaction();

            $updateData = $request->only([
                'title', 'description', 'status', 'cover_image_path', 'youtube_url', 'settings', 'slug',
            ]);

            if (!$request->filled('slug') && $request->filled('title')) {
                $updateData['slug'] = SalesForm::generateUniqueSlug($request->title, $form->id);
            }

            $form->update($updateData);

            if ($request->has('fields')) {
                $this->syncFields($form, $request->input('fields', []), true);
            }
            if ($request->has('product_ids')) {
                $this->syncProducts($form, $request->input('product_ids', []));
            }
            if ($request->has('access_blocks')) {
                $this->syncAccessBlocks($form, $request->input('access_blocks', []), true);
            }

            DB::commit();

            $form->load(['fields', 'products', 'accessBlocks']);

            return response()->json([
                'success' => true,
                'message' => 'Sales form updated successfully',
                'data' => $form,
                'public_url' => $form->public_url,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update sales form: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete a sales form.
     *
     * DELETE /api/sales-forms/{id}
     */
    public function destroy($id)
    {
        $form = SalesForm::findOrFail($id);
        $form->delete();

        return response()->json([
            'success' => true,
            'message' => 'Sales form deleted successfully',
        ]);
    }

    /**
     * Publish (or unpublish) a sales form.
     *
     * POST /api/sales-forms/{id}/publish
     */
    public function publish(Request $request, $id)
    {
        $form = SalesForm::findOrFail($id);

        $status = $request->get('status', SalesForm::STATUS_PUBLISHED);
        if (!in_array($status, [SalesForm::STATUS_PUBLISHED, SalesForm::STATUS_DRAFT, SalesForm::STATUS_ARCHIVED])) {
            $status = SalesForm::STATUS_PUBLISHED;
        }

        $form->update(['status' => $status]);

        return response()->json([
            'success' => true,
            'message' => 'Sales form ' . $status,
            'data' => $form,
            'public_url' => $form->public_url,
        ]);
    }

    /**
     * Products attachable to a sales form (current environment, course-bearing).
     *
     * GET /api/sales-forms/attachable-products
     */
    public function attachableProducts(Request $request)
    {
        $query = Product::query()->with(['courses:id,title']);

        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        $products = $query->orderBy('name')->limit(100)->get(['id', 'name', 'slug', 'price', 'currency', 'thumbnail_path']);

        return response()->json([
            'success' => true,
            'data' => $products,
        ]);
    }

    /**
     * Course structure (blocks + activities) for the access-control selector.
     *
     * GET /api/sales-forms/course-structure/{courseId}
     */
    public function courseStructure($courseId)
    {
        $course = Course::with(['template.blocks' => function ($q) {
            $q->orderBy('order')->with(['activities' => function ($a) {
                $a->orderBy('order');
            }]);
        }])->findOrFail($courseId);

        return response()->json([
            'success' => true,
            'data' => [
                'course_id' => $course->id,
                'title' => $course->title,
                'blocks' => optional($course->template)->blocks ?? [],
            ],
        ]);
    }

    /**
     * Aggregate analytics across all forms in the environment.
     *
     * GET /api/sales-forms/analytics/summary
     */
    public function analyticsSummary()
    {
        $forms = SalesForm::query()->withCount(['submissions'])->get();

        $formIds = $forms->pluck('id');
        $totalViews = $forms->sum('views_count');
        $totalSubmissions = $forms->sum('submissions_count');

        $completedOrders = \App\Models\Order::query()
            ->whereIn('sales_form_submission_id', function ($q) use ($formIds) {
                $q->select('id')->from('sales_form_submissions')->whereIn('sales_form_id', $formIds);
            })
            ->where('status', \App\Models\Order::STATUS_COMPLETED);

        return response()->json([
            'success' => true,
            'data' => [
                'forms_count' => $forms->count(),
                'published_count' => $forms->where('status', SalesForm::STATUS_PUBLISHED)->count(),
                'total_views' => $totalViews,
                'total_submissions' => $totalSubmissions,
                'conversion_rate' => $totalViews > 0 ? round(($totalSubmissions / $totalViews) * 100, 2) : 0,
                'completed_orders' => (clone $completedOrders)->count(),
                'revenue' => (clone $completedOrders)->sum('total_amount'),
                'forms' => $forms,
            ],
        ]);
    }

    /**
     * Per-form analytics.
     *
     * GET /api/sales-forms/{id}/analytics
     */
    public function analytics($id)
    {
        $form = SalesForm::withCount('submissions')->findOrFail($id);

        $submissionIds = $form->submissions()->pluck('id');
        $ordersQuery = \App\Models\Order::query()->whereIn('sales_form_submission_id', $submissionIds);

        return response()->json([
            'success' => true,
            'data' => [
                'form_id' => $form->id,
                'title' => $form->title,
                'views' => $form->views_count,
                'submissions' => $form->submissions_count,
                'conversion_rate' => $form->views_count > 0
                    ? round(($form->submissions_count / $form->views_count) * 100, 2) : 0,
                'pending_orders' => (clone $ordersQuery)->where('status', \App\Models\Order::STATUS_PENDING)->count(),
                'completed_orders' => (clone $ordersQuery)->where('status', \App\Models\Order::STATUS_COMPLETED)->count(),
                'revenue' => (clone $ordersQuery)->where('status', \App\Models\Order::STATUS_COMPLETED)->sum('total_amount'),
            ],
        ]);
    }

    /**
     * Paginated submissions for a form.
     *
     * GET /api/sales-forms/{id}/submissions
     */
    public function submissions(Request $request, $id)
    {
        $form = SalesForm::findOrFail($id);

        $submissions = $form->submissions()
            ->with(['orders:id,sales_form_submission_id,order_number,status,total_amount,currency'])
            ->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'fields' => $form->fields()->get(['field_key', 'label', 'type']),
            'data' => $submissions->items(),
            'meta' => [
                'current_page' => $submissions->currentPage(),
                'last_page' => $submissions->lastPage(),
                'per_page' => $submissions->perPage(),
                'total' => $submissions->total(),
            ],
        ]);
    }

    /**
     * Export submissions as CSV, one column per form field.
     *
     * GET /api/sales-forms/{id}/submissions/export
     */
    public function exportSubmissions($id)
    {
        $form = SalesForm::with('fields')->findOrFail($id);

        $fields = $form->fields; // ordered
        $filename = 'sales-form-' . $form->slug . '-submissions.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function () use ($form, $fields) {
            $out = fopen('php://output', 'w');

            $header = ['Submission ID', 'Access Code', 'Name', 'Email', 'Phone', 'Status', 'Submitted At'];
            foreach ($fields as $field) {
                $header[] = $field->label;
            }
            $header[] = 'Orders';
            fputcsv($out, $header);

            $form->submissions()->with('orders:id,sales_form_submission_id,order_number,status')
                ->orderBy('created_at', 'desc')
                ->chunk(200, function ($rows) use ($out, $fields) {
                    foreach ($rows as $submission) {
                        $answers = $submission->answers ?? [];
                        $line = [
                            $submission->id,
                            $submission->access_code,
                            $submission->name,
                            $submission->email,
                            $submission->phone,
                            $submission->status,
                            optional($submission->created_at)->toDateTimeString(),
                        ];
                        foreach ($fields as $field) {
                            $value = $answers[$field->field_key] ?? '';
                            $line[] = is_array($value) ? implode(', ', $value) : $value;
                        }
                        $line[] = $submission->orders
                            ->map(fn ($o) => $o->order_number . ' (' . $o->status . ')')
                            ->implode('; ');
                        fputcsv($out, $line);
                    }
                });

            fclose($out);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Shared validation rules for store/update.
     */
    private function validator(Request $request, ?SalesForm $form = null)
    {
        return Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'slug' => [
                'nullable',
                'string',
                'max:255',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('sales_forms', 'slug')->ignore($form?->id),
            ],
            'description' => 'nullable|string',
            'status' => ['nullable', Rule::in([SalesForm::STATUS_DRAFT, SalesForm::STATUS_PUBLISHED, SalesForm::STATUS_ARCHIVED])],
            'cover_image_path' => 'nullable|string|max:2048',
            'youtube_url' => 'nullable|url|max:2048',
            'settings' => 'nullable|array',
            'fields' => 'nullable|array',
            'fields.*.type' => 'required|string|max:50',
            'fields.*.label' => 'required|string|max:255',
            'fields.*.field_key' => 'nullable|string|max:255',
            'fields.*.placeholder' => 'nullable|string|max:255',
            'fields.*.help_text' => 'nullable|string|max:500',
            'fields.*.is_required' => 'nullable|boolean',
            'fields.*.order' => 'nullable|integer',
            'fields.*.options' => 'nullable|array',
            'product_ids' => 'nullable|array',
            'product_ids.*' => 'integer|exists:products,id',
            'access_blocks' => 'nullable|array',
            'access_blocks.*.course_id' => 'required|integer|exists:courses,id',
            'access_blocks.*.block_id' => 'nullable|integer|exists:blocks,id',
            'access_blocks.*.activity_id' => 'nullable|integer|exists:activities,id',
        ]);
    }

    private function normalizeSlugInput(Request $request): void
    {
        if ($request->has('slug')) {
            $request->merge([
                'slug' => Str::slug((string) $request->input('slug')),
            ]);
        }
    }

    private function syncFields(SalesForm $form, array $fields, bool $replace = false): void
    {
        if ($replace) {
            $form->fields()->delete();
        }

        foreach ($fields as $i => $field) {
            $form->fields()->create([
                'type' => $field['type'],
                'field_key' => $field['field_key'] ?? null,
                'label' => $field['label'],
                'placeholder' => $field['placeholder'] ?? null,
                'help_text' => $field['help_text'] ?? null,
                'is_required' => $field['is_required'] ?? false,
                'order' => $field['order'] ?? $i,
                'options' => $field['options'] ?? null,
            ]);
        }
    }

    private function syncProducts(SalesForm $form, array $productIds): void
    {
        $form->products()->sync($productIds);
    }

    private function syncAccessBlocks(SalesForm $form, array $blocks, bool $replace = false): void
    {
        if ($replace) {
            $form->accessBlocks()->delete();
        }

        foreach ($blocks as $block) {
            $form->accessBlocks()->create([
                'course_id' => $block['course_id'],
                'block_id' => $block['block_id'] ?? null,
                'activity_id' => $block['activity_id'] ?? null,
            ]);
        }
    }
}
