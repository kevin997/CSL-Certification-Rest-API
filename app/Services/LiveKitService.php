<?php

namespace App\Services;

use Agence104\LiveKit\AccessToken;
use Agence104\LiveKit\AccessTokenOptions;
use Agence104\LiveKit\VideoGrant;

class LiveKitService
{
    private string $apiKey;
    private string $apiSecret;
    private string $serverUrl;

    public function __construct()
    {
        $this->apiKey = config('services.livekit.api_key');
        $this->apiSecret = config('services.livekit.api_secret');
        $this->serverUrl = config('services.livekit.server_url', 'ws://localhost:7880');
    }

    /**
     * Generate a token for a participant to join a room.
     *
     * @param string $roomName
     * @param string $participantIdentity
     * @param string $participantName
     * @param bool $canPublish Whether the participant can publish audio/video
     * @param int $ttl Token TTL in seconds (default 6 hours)
     * @return string JWT token
     */
    public function generateToken(
        string $roomName,
        string $participantIdentity,
        string $participantName,
        bool $canPublish = false,
        int $ttl = 21600
    ): string {
        $tokenOptions = (new AccessTokenOptions())
            ->setIdentity($participantIdentity)
            ->setName($participantName)
            ->setTtl($ttl);

        $videoGrant = (new VideoGrant())
            ->setRoomJoin()
            ->setRoomName($roomName)
            ->setCanPublish($canPublish)
            ->setCanSubscribe()
            ->setCanPublishData();

        $token = (new AccessToken($this->apiKey, $this->apiSecret))
            ->init($tokenOptions)
            ->setGrant($videoGrant);

        return $token->toJwt();
    }

    /**
     * Generate a token for a host (can publish).
     */
    public function generateHostToken(
        string $roomName,
        string $participantIdentity,
        string $participantName,
        int $ttl = 21600
    ): string {
        return $this->generateToken($roomName, $participantIdentity, $participantName, true, $ttl);
    }

    /**
     * Generate a token for a viewer (cannot publish).
     */
    public function generateViewerToken(
        string $roomName,
        string $participantIdentity,
        string $participantName,
        int $ttl = 21600
    ): string {
        return $this->generateToken($roomName, $participantIdentity, $participantName, false, $ttl);
    }

    /**
     * Get the WebSocket URL for connecting to LiveKit.
     */
    public function getServerUrl(): string
    {
        return $this->serverUrl;
    }

    /**
     * Verify a webhook signature from LiveKit.
     *
     * @param string $body Raw request body
     * @param string $signature Authorization header value
     * @return bool
     */
    public function verifyWebhookSignature(string $body, string $signature): bool
    {
        // LiveKit webhook verification
        // The signature is a SHA256 HMAC of the body using the API secret
        $expectedSignature = base64_encode(hash_hmac('sha256', $body, $this->apiSecret, true));
        
        return hash_equals($expectedSignature, $signature);
    }
}
