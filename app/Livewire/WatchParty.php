<?php

namespace App\Livewire;

use App\Events\WatchPartyReset;
use App\Events\WatchPartyUpdated;
use App\Models\User;
use App\Services\Hyperbeam;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * The "Sala" watch-party room. One shared Hyperbeam VM that everyone joins.
 *
 * Real-time presence + turn-taking control + a consensus "nova sessão" vote are
 * modelled on the wheel (App\Livewire\Wheel): a heartbeat table + wire:poll + Pusher.
 *
 * Control is COOPERATIVE: exactly one controller at a time, tracked here and honoured
 * by each browser (non-controllers show a local overlay that blocks their mouse). The
 * Hyperbeam admin_token is never sent to the browser; host powers are enforced here.
 */
#[Layout('components.layouts.app', ['title' => 'Sala'])]
class WatchParty extends Component
{
    private const PRESENCE_WINDOW = 15;   // seconds since last heartbeat to still count as present
    private const RESET_TTL       = 30;   // seconds a reset request stays open before expiring
    private const ROOM_KEY        = 'watch_party:room';

    public ?string $embedUrl = null;
    public bool $configured = false;

    public bool $isHost = false;
    public bool $controlLocked = false;
    public ?int $controllerId = null;

    /** @var array<int,array{id:int,name:string,picture:?string,visible:bool,isController:bool}> */
    public array $members = [];

    public ?int $resetRequestedBy = null;
    public ?string $resetRequesterName = null;
    public ?int $resetFps = null;
    public ?string $resetResolution = null;
    public int $resetYes = 0;
    public int $resetNeeded = 0;
    public ?bool $myVote = null;

    public function mount(Hyperbeam $hb): void
    {
        $this->configured = $hb->configured();
        $session = $hb->sharedSession();
        $this->embedUrl = $session['embed_url'] ?? null;

        if ($this->embedUrl) {
            $this->mutateRoom(function (array $room) {
                if (empty($room['hostId'])) {
                    $room['hostId'] = auth()->id();
                }
                if (empty($room['controllerId'])) {
                    $room['controllerId'] = $room['hostId'];
                }

                return $room;
            });

            $this->heartbeat(true);
            $this->refresh();
        }
    }

    public function poll(): void
    {
        $this->heartbeat();
        $this->refresh();
    }

    // ---- presence -------------------------------------------------------------

    private function heartbeat(bool $fresh = false): void
    {
        DB::table('watch_party_members')->upsert(
            ['user_id' => auth()->id(), 'visible' => true, 'reset_vote' => null, 'heartbeat_at' => now()],
            ['user_id'],
            // On mount reset visibility to true; on plain polls only bump the heartbeat so the
            // client's real tab-visibility (set via setVisible) is preserved.
            $fresh ? ['visible', 'reset_vote', 'heartbeat_at'] : ['heartbeat_at'],
        );
    }

    public function setVisible(bool $visible): void
    {
        DB::table('watch_party_members')->upsert(
            ['user_id' => auth()->id(), 'visible' => $visible, 'reset_vote' => null, 'heartbeat_at' => now()],
            ['user_id'],
            ['visible', 'heartbeat_at'],
        );

        $this->refresh();
        $this->broadcastUpdate();
    }

    private function refresh(): void
    {
        $cutoff = now()->subSeconds(self::PRESENCE_WINDOW);

        $rows = DB::table('watch_party_members')
            ->join('users', 'users.id', '=', 'watch_party_members.user_id')
            ->where('watch_party_members.heartbeat_at', '>=', $cutoff)
            ->orderBy('watch_party_members.created_at')   // earliest-joined first (host fallback order)
            ->select('users.id', 'users.name', 'users.profile_picture', 'watch_party_members.visible', 'watch_party_members.reset_vote')
            ->get();

        $presentIds = $rows->pluck('id')->map(fn ($id) => (int) $id)->all();

        $room = $this->room();

        // Only take the write lock when host/controller/reset actually need fixing.
        $resetStale = ! empty($room['reset'])
            && (now()->timestamp - (int) ($room['reset']['requestedAt'] ?? 0)) > self::RESET_TTL;
        $needsFix = empty($room['hostId']) || ! in_array($room['hostId'], $presentIds, true)
            || empty($room['controllerId']) || ! in_array($room['controllerId'], $presentIds, true)
            || $resetStale;

        if ($needsFix) {
            $room = $this->mutateRoom(function (array $room) use ($presentIds) {
                if (empty($room['hostId']) || ! in_array($room['hostId'], $presentIds, true)) {
                    $room['hostId'] = $presentIds[0] ?? $room['hostId'] ?? auth()->id();
                }
                if (empty($room['controllerId']) || ! in_array($room['controllerId'], $presentIds, true)) {
                    $room['controllerId'] = $room['hostId'];
                }
                if (! empty($room['reset'])
                    && (now()->timestamp - (int) ($room['reset']['requestedAt'] ?? 0)) > self::RESET_TTL) {
                    $room['reset'] = null;
                }

                return $room;
            });
        }

        $this->controllerId  = (int) $room['controllerId'];
        $this->controlLocked = (bool) $room['controlLocked'];
        $this->isHost        = auth()->id() === (int) $room['hostId'];

        $this->members = $rows->map(fn ($u) => [
            'id'           => (int) $u->id,
            'name'         => $u->name,
            'picture'      => $u->profile_picture,
            'visible'      => (bool) $u->visible,
            'isController' => (int) $u->id === (int) $room['controllerId'],
        ])->values()->all();

        $reset = $room['reset'] ?? null;
        if ($reset) {
            $this->resetRequestedBy   = (int) $reset['requestedBy'];
            $this->resetRequesterName = optional($rows->firstWhere('id', $reset['requestedBy']))->name
                ?? User::find($reset['requestedBy'])?->name;
            $this->resetFps           = (int) $reset['fps'];
            $this->resetResolution    = $reset['width'].'x'.$reset['height'];
            $this->resetYes           = $rows->filter(fn ($u) => (int) $u->reset_vote === 1)->count();
            $this->resetNeeded        = (int) ceil(0.8 * max(count($presentIds), 1));

            $mine = $rows->firstWhere('id', auth()->id());
            $this->myVote = ($mine && $mine->reset_vote !== null) ? (bool) $mine->reset_vote : null;
        } else {
            $this->resetRequestedBy   = null;
            $this->resetRequesterName = null;
            $this->resetFps           = null;
            $this->resetResolution    = null;
            $this->resetYes           = 0;
            $this->resetNeeded        = 0;
            $this->myVote             = null;
        }
    }

    // ---- control (turn-taking) ------------------------------------------------

    public function pfpClick(int $userId): void
    {
        $room = $this->room();

        if (! $this->isPresent($userId)) {
            return;
        }

        if (! empty($room['controlLocked'])) {
            // Locked: only the host may assign control.
            if (auth()->id() === (int) $room['hostId']) {
                $this->setController($userId);
            }
        } elseif ($userId === auth()->id()) {
            // Unlocked: any user takes control by clicking their own pfp.
            $this->setController($userId);
        }
    }

    public function toggleLock(): void
    {
        $room = $this->room();
        if (auth()->id() !== (int) $room['hostId']) {
            return;
        }

        $this->mutateRoom(function (array $room) {
            $room['controlLocked'] = ! ($room['controlLocked'] ?? false);
            if ($room['controlLocked']) {
                $room['controllerId'] = $room['hostId'];   // host takes control when locking
            }

            return $room;
        });

        $this->refresh();
        $this->broadcastUpdate();
    }

    private function setController(int $userId): void
    {
        $this->mutateRoom(function (array $room) use ($userId) {
            $room['controllerId'] = $userId;

            return $room;
        });

        $this->refresh();
        $this->broadcastUpdate();
    }

    // ---- consensus reset ------------------------------------------------------

    public function requestReset(int $fps, int $width, int $height): void
    {
        $fps    = max(24, min(60, $fps));
        $width  = $this->round4($width);
        $height = $this->round4($height);

        $this->mutateRoom(function (array $room) use ($fps, $width, $height) {
            $room['reset'] = [
                'requestedBy' => auth()->id(),
                'requestedAt' => now()->timestamp,
                'fps'         => $fps,
                'width'       => $width,
                'height'      => $height,
            ];

            return $room;
        });

        // Clear everyone's vote, then record the requester's implicit "yes".
        DB::table('watch_party_members')->update(['reset_vote' => null]);
        DB::table('watch_party_members')->where('user_id', auth()->id())->update(['reset_vote' => true]);

        $this->refresh();
        $this->broadcastUpdate();
        $this->maybeFinalize();   // e.g. alone in the room -> passes immediately
    }

    public function voteReset(bool $agree): void
    {
        if (empty($this->room()['reset'])) {
            return;
        }

        DB::table('watch_party_members')
            ->where('user_id', auth()->id())
            ->update(['reset_vote' => $agree, 'heartbeat_at' => now()]);

        $this->refresh();
        $this->broadcastUpdate();
        $this->maybeFinalize();
    }

    public function cancelReset(): void
    {
        $room = $this->room();
        $reset = $room['reset'] ?? null;
        if (! $reset) {
            return;
        }
        if (auth()->id() !== (int) $reset['requestedBy'] && auth()->id() !== (int) $room['hostId']) {
            return;
        }

        $this->mutateRoom(function (array $room) {
            $room['reset'] = null;

            return $room;
        });
        DB::table('watch_party_members')->update(['reset_vote' => null]);

        $this->refresh();
        $this->broadcastUpdate();
    }

    private function maybeFinalize(): void
    {
        if ($this->resetRequestedBy !== null && $this->resetNeeded > 0 && $this->resetYes >= $this->resetNeeded) {
            $this->finalizeReset();
        }
    }

    private function finalizeReset(): void
    {
        $done = Cache::lock('watch_party:reset:lock', 20)->get(function () {
            $reset = $this->room()['reset'] ?? null;
            if (! $reset) {
                return false;   // another client already finalized
            }

            $hb  = app(Hyperbeam::class);
            $old = Cache::get('hyperbeam:room');
            if (is_array($old) && ! empty($old['session_id'])) {
                $hb->terminate($old['session_id']);
            }
            $hb->startAndStore($reset['fps'], $reset['width'], $reset['height']);

            $this->mutateRoom(function (array $room) {
                $room['reset'] = null;
                $room['controllerId'] = $room['hostId'];

                return $room;
            });
            DB::table('watch_party_members')->update(['reset_vote' => null]);

            return true;
        });

        if ($done) {
            try {
                broadcast(new WatchPartyReset());
            } catch (\Throwable) {
            }
            $this->redirect(route('watch-party'), navigate: true);
        }
    }

    // ---- realtime listeners ---------------------------------------------------

    #[On('echo:watch-party,.WatchPartyUpdated')]
    public function onUpdated(): void
    {
        $this->refresh();
    }

    #[On('echo:watch-party,.WatchPartyReset')]
    public function onReset(): void
    {
        $this->redirect(route('watch-party'), navigate: true);
    }

    // ---- room state (cache) ---------------------------------------------------

    private function room(): array
    {
        $room = Cache::get(self::ROOM_KEY);

        return (is_array($room) ? $room : []) + [
            'hostId'       => null,
            'controllerId' => null,
            'controlLocked' => false,
            'reset'        => null,
        ];
    }

    private function mutateRoom(callable $fn): array
    {
        return Cache::lock('watch_party:room:lock', 5)->block(4, function () use ($fn) {
            $room = $fn($this->room());
            Cache::put(self::ROOM_KEY, $room, now()->addHours(6));

            return $room;
        });
    }

    private function broadcastUpdate(): void
    {
        try {
            broadcast(new WatchPartyUpdated());
        } catch (\Throwable) {
        }
    }

    private function isPresent(int $userId): bool
    {
        return DB::table('watch_party_members')
            ->where('user_id', $userId)
            ->where('heartbeat_at', '>=', now()->subSeconds(self::PRESENCE_WINDOW))
            ->exists();
    }

    private function round4(int $n): int
    {
        return max(540, (int) (round($n / 4) * 4));
    }

    public function render()
    {
        return view('livewire.watch-party');
    }
}
