<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Thin client for Bunny Stream (https://docs.bunny.net/reference/api-overview).
 *
 * Responsibilities:
 *  - create a video object and hand the browser resumable (tus) upload credentials
 *  - mint short-lived, token-authenticated HLS playlist URLs for playback
 *  - delete videos
 *
 * All secrets live in config/services.php (`bunny_stream`). When the integration
 * is not configured, {@see enabled()} returns false and callers fall back to the
 * self-hosted media service.
 */
class BunnyStreamService
{
    private const TUS_ENDPOINT = 'https://video.bunnycdn.com/tusupload';
    private const API_BASE = 'https://video.bunnycdn.com';

    public function enabled(): bool
    {
        $cfg = config('services.bunny_stream');

        return (bool) ($cfg['enabled'] ?? false)
            && !empty($cfg['library_id'])
            && !empty($cfg['api_key'])
            && !empty($cfg['cdn_hostname']);
    }

    public function tusEndpoint(): string
    {
        return self::TUS_ENDPOINT;
    }

    private function libraryId(): string
    {
        return (string) config('services.bunny_stream.library_id');
    }

    private function apiKey(): string
    {
        return (string) config('services.bunny_stream.api_key');
    }

    /**
     * Create a video object in the Bunny library. Returns the video GUID, or null
     * on failure (caller should surface an error).
     */
    public function createVideo(string $title): ?string
    {
        $collectionId = config('services.bunny_stream.collection_id');

        $body = ['title' => $title];
        if (!empty($collectionId)) {
            $body['collectionId'] = $collectionId;
        }

        try {
            $response = Http::withHeaders([
                'AccessKey' => $this->apiKey(),
                'Content-Type' => 'application/json',
            ])->post(self::API_BASE . "/library/{$this->libraryId()}/videos", $body);

            if (!$response->successful()) {
                Log::error('Bunny Stream createVideo failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return null;
            }

            return $response->json('guid');
        } catch (\Throwable $e) {
            Log::error('Bunny Stream createVideo exception', ['message' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Build the credentials the browser needs to perform a resumable tus upload
     * directly to Bunny. The AuthorizationSignature is
     *   SHA256( library_id + api_key + expiration + video_id ).
     *
     * @return array{upload_url:string, headers:array<string,string>, expires_at:int}
     */
    public function tusUploadParams(string $videoId, int $ttlSeconds = 7200): array
    {
        $expiration = time() + $ttlSeconds;
        $signature = hash('sha256', $this->libraryId() . $this->apiKey() . $expiration . $videoId);

        return [
            'upload_url' => self::TUS_ENDPOINT,
            'headers' => [
                'AuthorizationSignature' => $signature,
                'AuthorizationExpire' => (string) $expiration,
                'VideoId' => $videoId,
                'LibraryId' => $this->libraryId(),
            ],
            'expires_at' => $expiration,
        ];
    }

    /**
     * Mint a short-lived, token-authenticated HLS playlist URL.
     *
     * Uses Bunny's directory ("token_path") token authentication so a single
     * token authorizes the master playlist and every variant/segment underneath
     * `/{videoId}/`. The token is
     *   base64url( SHA256_raw( token_key + token_path + expires ) ).
     *
     * The frontend player must propagate the `token`, `token_path` and `expires`
     * query parameters onto every sub-request (see secure-video-player.tsx).
     *
     * @return array{stream_url:string, token:string, token_path:string, expires:int}
     */
    public function signedPlaylistUrl(string $videoId, int $ttlSeconds = 14400): array
    {
        $cdn = rtrim((string) config('services.bunny_stream.cdn_hostname'), '/');
        $tokenKey = (string) config('services.bunny_stream.token_auth_key');
        $expires = time() + $ttlSeconds;
        $tokenPath = "/{$videoId}/";

        $raw = hash('sha256', $tokenKey . $tokenPath . $expires, true);
        $token = rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');

        $streamUrl = sprintf(
            'https://%s/%s/playlist.m3u8?token=%s&token_path=%s&expires=%d',
            $cdn,
            $videoId,
            $token,
            rawurlencode($tokenPath),
            $expires
        );

        return [
            'stream_url' => $streamUrl,
            'token' => $token,
            'token_path' => $tokenPath,
            'expires' => $expires,
        ];
    }

    public function deleteVideo(string $videoId): bool
    {
        try {
            $response = Http::withHeaders(['AccessKey' => $this->apiKey()])
                ->delete(self::API_BASE . "/library/{$this->libraryId()}/videos/{$videoId}");

            return $response->successful();
        } catch (\Throwable $e) {
            Log::error('Bunny Stream deleteVideo exception', ['message' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * Verify a Bunny webhook. Bunny does not sign webhooks, so we protect the
     * endpoint with a shared secret passed as a query string / header that we
     * compare in constant time.
     */
    public function verifyWebhookSecret(?string $provided): bool
    {
        $expected = (string) config('services.bunny_stream.webhook_secret', '');
        if ($expected === '') {
            return false;
        }

        return is_string($provided) && hash_equals($expected, $provided);
    }
}
