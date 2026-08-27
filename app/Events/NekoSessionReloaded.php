<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcast after an admin resets the Neko room so every open Sala remounts its
 * iframe instead of sitting on a dead stream. Carries no payload — the signal is
 * the whole message.
 *
 * Implements ShouldBroadcastNow (not ShouldBroadcast) so the Pusher call happens
 * during the request instead of being queued — shared hosting has no queue worker.
 */
class NekoSessionReloaded implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function broadcastOn(): Channel
    {
        return new Channel('neko');
    }

    public function broadcastAs(): string
    {
        return 'NekoSessionReloaded';
    }
}
