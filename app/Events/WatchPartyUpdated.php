<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcast that the Sala's shared state changed (presence, controller, lock, or a
 * pending reset vote) so every open room re-syncs immediately. ShouldBroadcastNow
 * (not queued) — shared hosting has no queue worker. Polling is the fallback.
 */
class WatchPartyUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function broadcastOn(): Channel
    {
        return new Channel('watch-party');
    }

    public function broadcastAs(): string
    {
        return 'WatchPartyUpdated';
    }
}
