<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcast the moment a movie is drawn so every open wheel spins in sync.
 *
 * Implements ShouldBroadcastNow (not ShouldBroadcast) so the publish happens
 * during the request instead of being queued — no queue worker runs in the container.
 */
class WheelSpun implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $drawId,
        public float $targetAngle,
    ) {}

    public function broadcastOn(): Channel
    {
        return new Channel('wheel');
    }

    public function broadcastAs(): string
    {
        return 'WheelSpun';
    }
}
