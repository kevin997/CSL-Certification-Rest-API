<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MediaProcessingStatusUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $mediaAssetId;
    public string $uploadId;
    public string $status;
    public array $processingMeta;

    /**
     * Create a new event instance.
     */
    public function __construct(int $mediaAssetId, string $uploadId, string $status, array $processingMeta = [])
    {
        $this->mediaAssetId = $mediaAssetId;
        $this->uploadId = $uploadId;
        $this->status = $status;
        $this->processingMeta = $processingMeta;
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): Channel
    {
        return new Channel('media-processing');
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'status.updated';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'media_asset_id' => $this->mediaAssetId,
            'upload_id' => $this->uploadId,
            'status' => $this->status,
            'processing_meta' => $this->processingMeta,
        ];
    }
}
