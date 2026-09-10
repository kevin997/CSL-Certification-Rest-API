<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\MediaAsset;
use App\Services\BunnyStreamService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class MediaAssetController extends Controller
{
    protected $mediaServiceUrl;

    protected BunnyStreamService $bunny;

    public function __construct(BunnyStreamService $bunny)
    {
        // Ideally from config
        $this->mediaServiceUrl = rtrim((string) config('services.media_service.url', 'http://localhost:8001'), '/');
        $this->bunny = $bunny;
    }

    protected function mediaServiceBaseUrl(): string
    {
        return rtrim((string) config('services.media_service.url', $this->mediaServiceUrl), '/');
    }

    /**
     * Headers proving this request came from us, for the media service routes
     * that destroy data.
     *
     * Those endpoints have no user session to authenticate against and were,
     * until now, reachable by anyone who could resolve the host. The timestamp
     * is signed along with the path so a captured DELETE cannot be replayed.
     */
    protected function mediaServiceSignature(string $method, string $uri, string $body = ''): array
    {
        $secret = (string) config('services.media_service.secret', '');
        $timestamp = (string) time();
        // $uri is the exact path+query being requested, so a captured call
        // cannot be re-pointed at another tenant.
        $canonical = implode("\n", [strtoupper($method), $uri, $timestamp, hash('sha256', $body)]);

        return [
            'X-Media-Service-Timestamp' => $timestamp,
            'X-Media-Service-Signature' => hash_hmac('sha256', $canonical, $secret),
        ];
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

        $environmentId = $request->user()->environment_id ?? 1;

        // Route VIDEO to Bunny Stream when configured. Audio stays on the
        // self-hosted media service (Bunny Stream is video-only).
        if ($validated['type'] === 'video' && $this->bunny->enabled()) {
            return $this->initBunnyUpload($request, $validated, $environmentId);
        }

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
     * Initialize a resumable direct-to-MinIO multipart upload (proxy to Media Service).
     *
     * Creates the local MediaAsset and returns the media-service base URL so the
     * browser can call /sign and /parts directly, uploading each part straight to
     * MinIO via presigned URLs. Bytes never pass through this API or PHP.
     */
    public function initMultipartUpload(Request $request)
    {
        $validated = $request->validate([
            'file_name' => 'required|string',
            'file_size' => 'required|integer',
            'mime_type' => 'required|string',
            'title' => 'required|string',
            'type' => 'required|in:audio,video',
        ]);

        $environmentId = $request->user()->environment_id ?? 1;
        $baseUrl = $this->mediaServiceBaseUrl();

        $response = Http::acceptJson()->post("{$baseUrl}/api/media/multipart/init", [
            'environment_id' => $environmentId,
            'file_name' => $validated['file_name'],
            'file_size' => $validated['file_size'],
            'mime_type' => $validated['mime_type'],
        ]);

        if (!$response->successful()) {
            return response()->json(['error' => 'Media Service multipart init failed', 'details' => $response->json()], $response->status());
        }

        $data = $response->json();

        $mediaAsset = MediaAsset::create([
            'environment_id' => $environmentId,
            'owner_user_id' => $request->user()->id,
            'title' => $validated['title'] ?? $validated['file_name'],
            'type' => $validated['type'],
            'mime_type' => $validated['mime_type'],
            'size' => $validated['file_size'],
            'status' => 'pending',
            'meta' => ['upload_id' => $data['upload_id'] ?? null, 'multipart' => true],
        ]);

        return response()->json(array_merge(
            ['media_asset' => $mediaAsset, 'media_service_url' => $baseUrl],
            $data // upload_id, key, s3_upload_id, part_size, part_count, bucket
        ));
    }

    /**
     * Complete a multipart upload (proxy to Media Service) and update the asset.
     */
    public function completeMultipartUpload(Request $request, $id)
    {
        $validated = $request->validate([
            'parts' => 'required|array|min:1',
            'parts.*.part_number' => 'required|integer|min:1',
            'parts.*.etag' => 'required|string',
        ]);

        $mediaAsset = is_numeric($id) ? MediaAsset::find($id) : null;
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

        $baseUrl = $this->mediaServiceBaseUrl();
        $response = Http::acceptJson()->post("{$baseUrl}/api/media/multipart/{$uploadId}/complete", [
            'parts' => $validated['parts'],
        ]);

        if (!$response->successful()) {
            return response()->json(['error' => 'Media Service multipart complete failed', 'details' => $response->json()], 500);
        }

        $status = $response->json()['status'] ?? 'processing';
        $mediaAsset->update(['status' => $status]);

        return response()->json(['media_asset' => $mediaAsset->fresh()]);
    }

    /**
     * Abandon an in-flight multipart upload: have the media service drop the parts
     * MinIO is holding, then forget the asset. Only a 'pending' asset can be
     * aborted — once complete ran, the file belongs to the transcoder and
     * destroy() is the way out.
     */
    public function abortMultipartUpload(Request $request, $id)
    {
        $environmentId = $request->user()->environment_id ?? 1;
        $mediaAsset = MediaAsset::where('id', $id)
            ->where('environment_id', $environmentId)
            ->first();

        if (!$mediaAsset) {
            return response()->json(['error' => 'Media asset not found'], 404);
        }

        if ($mediaAsset->status !== 'pending') {
            return response()->json([
                'code' => 'upload_not_pending',
                'status' => $mediaAsset->status,
                'error' => 'Only an upload still in progress can be cancelled',
            ], 409);
        }

        $uploadId = $mediaAsset->meta['upload_id'] ?? null;
        if ($uploadId) {
            $baseUrl = $this->mediaServiceBaseUrl();
            $uri = "/api/media/multipart/{$uploadId}/abort?environment_id={$environmentId}";
            $response = Http::acceptJson()
                ->withHeaders($this->mediaServiceSignature('POST', $uri))
                ->withBody('', 'application/json')
                ->post("{$baseUrl}{$uri}");

            // A 404 means the media service already forgot it; nothing left to free.
            if (!$response->successful() && $response->status() !== 404) {
                return response()->json(['error' => 'Media Service multipart abort failed', 'details' => $response->json()], 502);
            }
        }

        $mediaAsset->delete();

        return response()->json(['deleted' => true]);
    }

    /**
     * Initialize a resumable (tus) upload against Bunny Stream.
     *
     * Creates the Bunny video object, records a local MediaAsset, and returns the
     * tus endpoint + signed headers the browser needs to upload directly.
     */
    protected function initBunnyUpload(Request $request, array $validated, int $environmentId)
    {
        $videoId = $this->bunny->createVideo($validated['title'] ?? $validated['file_name']);

        if (!$videoId) {
            return response()->json(['error' => 'Failed to create video on Bunny Stream'], 502);
        }

        $tus = $this->bunny->tusUploadParams($videoId);

        $mediaAsset = MediaAsset::create([
            'environment_id' => $environmentId,
            'owner_user_id' => $request->user()->id,
            'provider' => 'bunny_stream',
            'provider_asset_id' => $videoId,
            'title' => $validated['title'] ?? $validated['file_name'],
            'type' => 'video',
            'mime_type' => $validated['mime_type'],
            'size' => $validated['file_size'],
            'status' => 'pending',
            // Keep upload_id mirroring the provider id so existing lookups work.
            'meta' => ['upload_id' => $videoId, 'provider' => 'bunny_stream'],
        ]);

        return response()->json([
            'media_asset' => $mediaAsset,
            'upload_id' => $videoId,
            'upload_url' => $tus['upload_url'],
            'upload_protocol' => 'tus',
            'headers' => $tus['headers'],
            'metadata' => [
                'title' => $mediaAsset->title,
                'filetype' => $validated['mime_type'],
            ],
            'expires_at' => $tus['expires_at'],
        ]);
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

        // Bunny Stream begins encoding automatically once the tus upload finishes.
        // There is nothing to "complete" — just mark it processing and wait for the
        // Bunny webhook to flip it to ready.
        if ($mediaAsset->provider === 'bunny_stream') {
            $mediaAsset->update(['status' => 'processing']);

            return response()->json(['media_asset' => $mediaAsset->fresh()]);
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

        // 1a. Bunny-backed assets are deleted from Bunny Stream.
        if ($mediaAsset->provider === 'bunny_stream') {
            $videoId = $mediaAsset->provider_asset_id ?: ($mediaAsset->meta['upload_id'] ?? null);
            if ($videoId) {
                $this->bunny->deleteVideo($videoId);
            }
            $mediaAsset->delete();

            return response()->json(['message' => 'Media asset deleted successfully']);
        }

        // 1. Delete from Media Service (if uploaded)
        $uploadId = $mediaAsset->meta['upload_id'] ?? null;
        if ($uploadId) {
            try {
                $baseUrl = $this->mediaServiceBaseUrl();
                // DELETE /api/media/{upload_id}; signed, and scoped to this
                // tenant. The media service refuses the call without both.
                $uri = "/api/media/{$uploadId}?environment_id={$environmentId}";

                Log::info('Deleting external media asset', ['upload_id' => $uploadId, 'environment_id' => $environmentId]);
                Http::acceptJson()
                    ->withHeaders($this->mediaServiceSignature('DELETE', $uri))
                    ->withBody('', 'application/json')
                    ->delete("{$baseUrl}{$uri}");
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

        // Restrict playback to the asset's own environment (multi-tenant boundary).
        $environmentId = $request->user()->environment_id ?? 1;
        if ((int) $mediaAsset->environment_id !== (int) $environmentId) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $uploadId = $mediaAsset->meta['upload_id'] ?? null;
        if (!$uploadId) {
            return response()->json(['error' => 'Asset not ready'], 400);
        }

        // Bunny Stream: return a short-lived, token-authenticated HLS URL.
        if ($mediaAsset->provider === 'bunny_stream') {
            $videoId = $mediaAsset->provider_asset_id ?: $uploadId;
            $signed = $this->bunny->signedPlaylistUrl($videoId);

            return response()->json([
                'token' => $signed['token'],
                'stream_url' => $signed['stream_url'],
                'token_path' => $signed['token_path'],
                'expires' => $signed['expires'],
                'type' => 'video',
            ]);
        }

        // A failed transcode is final on our side: no need to ask the media service.
        if ($mediaAsset->status === 'failed') {
            return $this->notPlayable($mediaAsset, 'media_failed');
        }

        // A pending or processing asset still goes to the media service: it is the
        // authority on whether the HLS exists, and our status can lag behind it
        // when the 'ready' webhook never arrived.
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
            $remote = is_array($response->json()) ? $response->json() : [];
            $code = $this->notPlayableCode($remote);

            if ($code === null) {
                return response()->json(
                    [
                        'error' => 'Failed to start playback session',
                        'details' => $remote,
                    ],
                    $response->status()
                );
            }

            // "Not ready" is a state, not an error: relay it as one the client can
            // branch on. When the media service knows the transcode failed and we
            // still say processing, its webhook was lost — take its word for it.
            if ($code === 'media_failed' && $mediaAsset->status !== 'failed') {
                $meta = $mediaAsset->meta ?? [];
                $meta['processing_meta'] = array_merge(
                    is_array($meta['processing_meta'] ?? null) ? $meta['processing_meta'] : [],
                    array_filter(['error' => $remote['reason'] ?? null])
                );
                $mediaAsset->update(['status' => 'failed', 'meta' => $meta]);
            }

            return $this->notPlayable($mediaAsset->fresh(), $code);
        }

        // The media service has the HLS. If our copy of the status never got the
        // 'ready' webhook, catch it up so the client stops polling.
        if ($mediaAsset->status !== 'ready') {
            $mediaAsset->update(['status' => 'ready']);
        }

        $data = $response->json();
        return response()->json([
            'token' => $data['token'] ?? null,
            'stream_url' => $data['manifest_url'] ?? ($data['stream_url'] ?? null),
            'type' => $data['type'] ?? 'video',
        ]);
    }

    /**
     * Translate the media service's refusal into our stable codes. Newer media
     * services send `code`; older ones only the text "Media not ready" (400).
     */
    private function notPlayableCode(array $remote): ?string
    {
        $code = $remote['code'] ?? null;
        if (in_array($code, ['media_not_ready', 'media_failed'], true)) {
            return $code;
        }

        return str_contains(strtolower((string) ($remote['error'] ?? '')), 'not ready')
            ? 'media_not_ready'
            : null;
    }

    /**
     * 409 with a machine-readable code and the asset's state, so the player shows
     * progress (or the failure and its reason) instead of a generic error.
     */
    private function notPlayable(MediaAsset $mediaAsset, string $code)
    {
        $processingMeta = $mediaAsset->meta['processing_meta'] ?? null;

        return response()->json([
            'code' => $code,
            'status' => $mediaAsset->status,
            'error' => $code === 'media_failed' ? 'Media processing failed' : 'Media is not ready yet',
            'reason' => is_array($processingMeta) ? ($processingMeta['error'] ?? null) : null,
        ], 409);
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

        // The transcoder probes the duration; the editor's duration field wants it.
        if (isset($processingMeta['duration']) && (int) $processingMeta['duration'] > 0) {
            $updateData['duration'] = (int) $processingMeta['duration'];
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

    /**
     * Bunny Stream webhook. Bunny POSTs { VideoLibraryId, VideoGuid, Status }
     * as encoding progresses. We protect the endpoint with a shared secret
     * (query param `secret` or header X-Bunny-Webhook-Secret) since Bunny does
     * not sign its webhooks.
     *
     * Bunny status codes: 0 Created, 1 Uploaded, 2 Processing, 3 Transcoding,
     * 4 Finished, 5 Error, 6 UploadFailed.
     */
    public function bunnyWebhook(Request $request)
    {
        $provided = $request->query('secret', $request->header('X-Bunny-Webhook-Secret'));
        if (!$this->bunny->verifyWebhookSecret(is_string($provided) ? $provided : null)) {
            return response()->json(['error' => 'Invalid webhook secret'], 403);
        }

        $payload = $request->json()->all();
        $videoGuid = $payload['VideoGuid'] ?? ($payload['videoGuid'] ?? null);
        $bunnyStatus = $payload['Status'] ?? ($payload['status'] ?? null);

        if (!$videoGuid) {
            return response()->json(['error' => 'Missing VideoGuid'], 422);
        }

        $mediaAsset = MediaAsset::where('provider', 'bunny_stream')
            ->where('provider_asset_id', $videoGuid)
            ->first();

        if (!$mediaAsset) {
            // Unknown video — acknowledge so Bunny stops retrying.
            return response()->json(['ok' => true]);
        }

        $status = match ((int) $bunnyStatus) {
            4 => 'ready',
            5, 6 => 'failed',
            default => 'processing',
        };

        $mediaAsset->update(['status' => $status]);

        Log::info('Bunny Stream webhook received', [
            'media_asset_id' => $mediaAsset->id,
            'video_guid' => $videoGuid,
            'bunny_status' => $bunnyStatus,
            'status' => $status,
        ]);

        broadcast(new \App\Events\MediaProcessingStatusUpdated(
            $mediaAsset->id,
            (string) ($mediaAsset->meta['upload_id'] ?? $videoGuid),
            $status,
            []
        ));

        return response()->json(['ok' => true]);
    }
}
