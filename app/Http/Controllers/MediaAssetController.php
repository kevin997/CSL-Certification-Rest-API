<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\MediaAsset;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class MediaAssetController extends Controller
{
    protected $mediaServiceUrl;

    public function __construct()
    {
        // Ideally from config
        $this->mediaServiceUrl = rtrim((string) config('services.media_service.url', 'http://localhost:8001'), '/');
    }

    protected function mediaServiceBaseUrl(): string
    {
        return rtrim((string) config('services.media_service.url', $this->mediaServiceUrl), '/');
    }

    /**
     * Initialize upload (proxy to Media Service)
     */
    public function initUpload(Request $request)
    {
        $validated = $request->validate([
            'file_name' => 'required|string',
            'file_size' => 'required|integer',
            'mime_type' => 'required|string',
            'title' => 'required|string',
            'type' => 'required|in:audio,video',
        ]);

        // Call Media Service to initialize upload
        $baseUrl = $this->mediaServiceBaseUrl();
        $url = "{$baseUrl}/api/media/uploads/init";

        Log::info('Media Service Request URL: ' . $url);

        $response = Http::acceptJson()->post($url, [
            'file_name' => $validated['file_name'],
            'mime_type' => $validated['mime_type'],
            'file_size' => $validated['file_size'],
            'environment_id' => $request->user()->environment_id ?? 1,
        ]);

        Log::info('Media Service Response Status: ' . $response->status());
        Log::info('Media Service Response Content-Type: ' . ($response->header('Content-Type') ?? ''));
        Log::info('Media Service Response Body: ' . $response->body());

        $contentType = (string) $response->header('Content-Type');
        if (str_contains($contentType, 'text/html') || str_starts_with(ltrim($response->body()), '<!DOCTYPE html')) {
            return response()->json([
                'error' => 'Unexpected response from Media Service (HTML). Check MEDIA_SERVICE_URL / routing.',
                'media_service_url' => $url,
            ], 502);
        }

        if (!$response->successful()) {
            return response()->json(
                ['error' => 'Media Service init failed', 'details' => $response->json()],
                $response->status()
            );
        }

        $data = $response->json();

        // Create local asset reference
        $mediaAsset = MediaAsset::create([
            'environment_id' => $request->user()->environment_id ?? 1,
            'owner_user_id' => $request->user()->id,
            'title' => $validated['title'] ?? $validated['file_name'],
            'type' => $validated['type'],
            'status' => 'pending',
            'meta' => ['upload_id' => $data['upload_id'] ?? null],
        ]);

        return response()->json(array_merge(
            ['media_asset' => $mediaAsset], // Include local asset
            $data // Merge upload_url, upload_id, etc. at root
        ));
    }

    /**
     * Complete upload (proxy to Media Service)
     */
    public function completeUpload(Request $request, $id)
    {
        $mediaAsset = null;

        if (is_numeric($id)) {
            $mediaAsset = MediaAsset::find($id);
        }

        if (!$mediaAsset) {
            $mediaAsset = MediaAsset::where('meta->upload_id', (string) $id)->first();
        }

        if (!$mediaAsset) {
            return response()->json(['error' => 'Media asset not found'], 404);
        }

        $uploadId = $mediaAsset->meta['upload_id'] ?? null;

        if (!$uploadId) {
            return response()->json(['error' => 'Invalid asset state'], 400);
        }

        // Call Media Service
        $baseUrl = $this->mediaServiceBaseUrl();
        $url = "{$baseUrl}/api/media/uploads/{$uploadId}/complete";

        Log::info('Media Service Request URL: ' . $url);

        $response = Http::acceptJson()->post($url);

        Log::info('Media Service Response Status: ' . $response->status());
        Log::info('Media Service Response Content-Type: ' . ($response->header('Content-Type') ?? ''));
        Log::info('Media Service Response Body: ' . $response->body());

        if (!$response->successful()) {
            return response()->json(['error' => 'Media Service processing failed', 'details' => $response->json()], 500);
        }

        $responseData = $response->json();
        $mediaStatus = $responseData['status'] ?? 'processing';

        // Audio files are marked as 'ready' immediately by the media service (no transcoding)
        $mediaAsset->update(['status' => $mediaStatus]);

        if ($mediaStatus === 'ready') {
            Log::info('Audio media ready immediately (no transcoding)', [
                'media_asset_id' => $mediaAsset->id,
                'upload_id' => $uploadId,
            ]);

            // Broadcast WebSocket event so frontend knows immediately
            broadcast(new \App\Events\MediaProcessingStatusUpdated(
                $mediaAsset->id,
                $uploadId,
                'ready',
                []
            ));
        }

        return response()->json(['media_asset' => $mediaAsset->fresh()]);
    }

    /**
     * List media assets
     */
    public function index(Request $request)
    {
        $environmentId = $request->user()->environment_id ?? 1;

        $assets = MediaAsset::where('environment_id', $environmentId)
            ->where('status', '!=', 'archived') // Example filter
            ->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 50));

        return response()->json($assets);
    }

    /**
     * Get media asset details
     */
    public function show(Request $request, $id)
    {
        $environmentId = $request->user()->environment_id ?? 1;
        return MediaAsset::where('id', $id)
            ->where('environment_id', $environmentId)
            ->firstOrFail();
    }

    /**
     * Delete a media asset
     */
    public function destroy(Request $request, $id)
    {
        $environmentId = $request->user()->environment_id ?? 1;
        $mediaAsset = MediaAsset::where('id', $id)
            ->where('environment_id', $environmentId)
            ->firstOrFail();

        // 1. Delete from Media Service (if uploaded)
        $uploadId = $mediaAsset->meta['upload_id'] ?? null;
        if ($uploadId) {
            try {
                $baseUrl = $this->mediaServiceBaseUrl();
                // Assuming DELETE /api/media/{upload_id} exists on the Media Service
                // Or /api/media/uploads/{upload_id} depending on how Media Service is structured
                // Based on initUpload being /api/media/uploads/init, let's guess /api/media/uploads/{uploadId} or just /api/media/{uploadId}
                // Let's assume standard REST resource: DELETE /api/media/{uploadId}
                $url = "{$baseUrl}/api/media/{$uploadId}";

                Log::info('Deleting external media asset: ' . $url);
                Http::acceptJson()->delete($url);
            } catch (\Exception $e) {
                // Log but continue to delete local record
                Log::error('Failed to delete remote media asset: ' . $e->getMessage());
            }
        }

        // 2. Delete local record
        $mediaAsset->delete();

        return response()->json(['message' => 'Media asset deleted successfully']);
    }

    /**
     * Get playback session
     */
    public function playbackSession(Request $request, $id)
    {
        $mediaAsset = MediaAsset::findOrFail($id);

        // Check access permissions
        // For now, allow owner
        // if ($mediaAsset->owner_user_id !== $request->user()->id) { abort(403); }

        $uploadId = $mediaAsset->meta['upload_id'] ?? null;
        if (!$uploadId) {
            return response()->json(['error' => 'Asset not ready'], 400);
        }

        // Call Media Service to get session
        $baseUrl = $this->mediaServiceBaseUrl();
        $url = "{$baseUrl}/api/media/{$uploadId}/playback-session";

        Log::info('Media Service Request URL: ' . $url);

        $response = Http::acceptJson()->post($url);

        $contentType = (string) $response->header('Content-Type');
        if (str_contains($contentType, 'text/html') || str_starts_with(ltrim($response->body()), '<!DOCTYPE html')) {
            return response()->json([
                'error' => 'Unexpected response from Media Service (HTML). Check MEDIA_SERVICE_URL / routing.',
                'media_service_url' => $url,
            ], 502);
        }

        if (!$response->successful()) {
            return response()->json(
                [
                    'error' => 'Failed to start playback session',
                    'details' => $response->json(),
                ],
                $response->status()
            );
        }

        $data = $response->json();
        return response()->json([
            'token' => $data['token'] ?? null,
            'stream_url' => $data['manifest_url'] ?? ($data['stream_url'] ?? null),
            'type' => $data['type'] ?? 'video',
        ]);
    }

    public function processingWebhook(Request $request)
    {
        $secret = (string) config('services.media_service.secret', '');
        if ($secret === '') {
            return response()->json(['error' => 'Webhook secret not configured'], 500);
        }

        $signature = (string) $request->header('X-Media-Service-Signature', '');
        $rawBody = $request->getContent();
        $expected = hash_hmac('sha256', $rawBody, $secret);

        if (!hash_equals($expected, $signature)) {
            return response()->json(['error' => 'Invalid signature'], 403);
        }

        $payload = $request->json()->all();
        $uploadId = null;
        if (isset($payload['upload_id'])) {
            $uploadId = (string)$payload['upload_id'];
        }
        $status = $payload['status'] ?? null;

        if ($uploadId === null || !Str::isUuid($uploadId)) {
            return response()->json(['error' => 'Invalid upload_id'], 422);
        }

        if (!in_array($status, ['ready', 'failed', 'processing'], true)) {
            return response()->json(['error' => 'Invalid status'], 422);
        }

        $mediaAsset = MediaAsset::where('meta->upload_id', $uploadId)->first();
        if (!$mediaAsset) {
            return response()->json(['error' => 'Media asset not found'], 404);
        }

        $processingMeta = is_array($payload['processing_meta'] ?? null) ? $payload['processing_meta'] : [];

        $nextMeta = $mediaAsset->meta ?? [];
        $nextMeta['processing_meta'] = $processingMeta;
        $nextMeta['media_upload_status'] = $status;

        // Extract size and mime_type from processing_meta if available
        $updateData = [
            'status' => $status,
            'meta' => $nextMeta,
        ];

        if (isset($processingMeta['file_size']) && $processingMeta['file_size'] > 0) {
            $updateData['size'] = $processingMeta['file_size'];
        }

        if (isset($processingMeta['mime_type']) && !empty($processingMeta['mime_type'])) {
            $updateData['mime_type'] = $processingMeta['mime_type'];
        }

        $mediaAsset->update($updateData);

        Log::info('Media processing webhook received', [
            'media_asset_id' => $mediaAsset->id,
            'upload_id' => $uploadId,
            'status' => $status,
        ]);

        // Broadcast WebSocket event for real-time updates
        broadcast(new \App\Events\MediaProcessingStatusUpdated(
            $mediaAsset->id,
            $uploadId,
            $status,
            $processingMeta
        ));

        return response()->json(['ok' => true]);
    }
}
