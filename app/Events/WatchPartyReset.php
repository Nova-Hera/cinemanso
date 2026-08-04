<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcast that a brand-new VM was created (the reset vote passed). Every open room
 * reloads to remount the new embed_url.
 */
class WatchPartyReset implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function broadcastOn(): Channel
    {
        return new Channel('watch-party');
    }

    public function broadcastAs(): string
    {
        return 'WatchPartyReset';
    }
}
