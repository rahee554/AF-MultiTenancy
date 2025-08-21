<?php

namespace ArtflowStudio\Tenancy\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TenantCreationProgress implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $progressKey;
    public array $progress;

    public function __construct(string $progressKey, array $progress)
    {
        $this->progressKey = $progressKey;
        $this->progress = $progress;
    }

    public function broadcastOn(): array
    {
        return [
            new Channel('tenant-creation.' . $this->progressKey),
            new Channel('admin-dashboard'), // For admin monitoring
        ];
    }

    public function broadcastAs(): string
    {
        return 'tenant.creation.progress';
    }

    public function broadcastWith(): array
    {
        return [
            'progress_key' => $this->progressKey,
            'message' => $this->progress['message'],
            'percentage' => $this->progress['percentage'],
            'timestamp' => $this->progress['timestamp'],
            'data' => $this->progress['data'] ?? [],
        ];
    }
}
