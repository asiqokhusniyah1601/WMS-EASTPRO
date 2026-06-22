<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use App\Services\DashboardInsightService;

class GlobalStockUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $metrics;
    public $insights;
    public $payload;

    /**
     * Create a new event instance.
     */
    public function __construct()
    {
        $service = new DashboardInsightService();

        // Full payload: a global slice plus a per-warehouse map, so connected
        // clients can re-render whichever view they currently have selected.
        $this->payload = $service->getBroadcastPayload();

        // Backward-compatible global properties.
        $this->metrics  = $this->payload['global']['metrics'];
        $this->insights = $this->payload['global']['insights'];
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('dashboard-updates'),
        ];
    }
}
