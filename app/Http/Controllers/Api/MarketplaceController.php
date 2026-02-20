<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Template;
use App\Services\KafkaProducerService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class MarketplaceController extends Controller
{
    /**
     * Publish a template to the marketplace via Kafka.
     */
    public function sellTemplate(Request $request, int $templateId)
    {
        $validator = Validator::make($request->all(), [
            'price' => 'required|numeric|min:0.01|max:99999.99',
            'marketplace_name' => 'required|string|max:255',
            'marketplace_description' => 'required|string|max:5000',
            'category' => 'nullable|string|max:100',
            'thumbnail_url' => 'nullable|url|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $template = Template::with('blocks')->findOrFail($templateId);

        // Only the template owner can sell it
        if ($template->created_by !== Auth::id()) {
            return response()->json([
                'status' => 'error',
                'message' => 'You do not have permission to sell this template',
            ], Response::HTTP_FORBIDDEN);
        }

        // Only published templates can be sold
        if ($template->status !== 'published') {
            return response()->json([
                'status' => 'error',
                'message' => 'Only published templates can be listed on the marketplace',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $user = Auth::user();

        $kafkaData = [
            'template_id' => $template->id,
            'template_title' => $template->title,
            'template_code' => $template->template_code,
            'template_description' => $template->description,
            'blocks_count' => $template->blocks->count(),

            'seller_user_id' => $user->id,
            'seller_name' => $user->name,
            'seller_email' => $user->email,
            'seller_company' => $user->company_name ?? $user->name,
            'seller_is_teacher' => method_exists($user, 'isTeacher') ? $user->isTeacher() : true,

            'price' => (float) $request->input('price'),
            'marketplace_name' => $request->input('marketplace_name'),
            'marketplace_description' => $request->input('marketplace_description'),
            'category' => $request->input('category', 'general'),
            'thumbnail_url' => $request->input('thumbnail_url', $template->thumbnail_path),
        ];

        $producer = new KafkaProducerService();
        $published = $producer->publishTemplateToMarketplace($kafkaData);

        if (!$published) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to publish template to marketplace. Please try again.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Template submitted to marketplace successfully. It will appear shortly.',
        ]);
    }
}
