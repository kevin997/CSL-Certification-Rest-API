<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProductReview;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;

class ProductReviewController extends Controller
{
    public function index(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'environment_id' => 'nullable|integer|exists:environments,id',
            'product_id' => 'nullable|integer|exists:products,id',
            'status' => 'nullable|string|in:pending,approved,rejected',
            'search' => 'nullable|string|max:255',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $environmentId = $request->input('environment_id') ?? session('current_environment_id');

        $query = ProductReview::query()
            ->with(['product:id,name,slug,thumbnail_path', 'user:id,name,email'])
            ->when($environmentId, fn ($query) => $query->where('environment_id', $environmentId))
            ->when($request->filled('product_id'), fn ($query) => $query->where('product_id', $request->input('product_id')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->input('status')))
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->input('search');
                $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('comment', 'like', "%{$search}%")
                        ->orWhereHas('product', fn ($query) => $query->where('name', 'like', "%{$search}%"));
                });
            })
            ->latest();

        return response()->json([
            'status' => 'success',
            'data' => $query->paginate($request->input('per_page', 15))->appends($request->query()),
        ]);
    }

    public function update(Request $request, int $id)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|string|in:pending,approved,rejected',
            'environment_id' => 'nullable|integer|exists:environments,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $environmentId = $request->input('environment_id') ?? session('current_environment_id');

        $review = ProductReview::query()
            ->when($environmentId, fn ($query) => $query->where('environment_id', $environmentId))
            ->findOrFail($id);

        $review->status = $request->input('status');
        $review->save();
        $review->load(['product:id,name,slug,thumbnail_path', 'user:id,name,email']);

        return response()->json([
            'status' => 'success',
            'message' => 'Review updated successfully',
            'data' => $review,
        ]);
    }

    public function destroy(Request $request, int $id)
    {
        $environmentId = $request->input('environment_id') ?? session('current_environment_id');

        $review = ProductReview::query()
            ->when($environmentId, fn ($query) => $query->where('environment_id', $environmentId))
            ->findOrFail($id);

        $review->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Review deleted successfully',
        ]);
    }
}
