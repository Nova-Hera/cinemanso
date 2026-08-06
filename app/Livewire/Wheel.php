<?php

namespace App\Livewire;

use App\Events\WheelSpun;
use App\Models\Movie;
use App\Models\WheelDraw;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;

#[Layout('components.layouts.app', ['title' => 'Roleta'])]
class Wheel extends Component
{
    public array  $segments       = [];
    public array  $watchlistList  = [];
    public int    $presentCount   = 0;
    public int    $readyCount      = 0;
    public bool   $iAmReady        = false;
    public array  $presentUsers    = [];
    public array  $presentUserIds  = [];
    public ?array $result          = null;
    public ?int   $drawId          = null;
    public ?float $targetAngle     = null;
    public float  $initRotation    = 0.0;
    public bool   $initShowResult  = false;
    public ?int   $lastAnimatedDrawId = null;

    private const RESULT_WINDOW_MIN = 10;   // how long the result card + winning layout stays shown
    private const SPIN_FRESH_SEC    = 15;   // a draw newer than this triggers the spin animation

    private array $palette = [
        '#facc15', '#f97316', '#6366f1',
        '#ec4899', '#06b6d4', '#f59e0b',
        '#8b5cf6', '#ef4444',
    ];

    public function mount(): void
    {
        $this->heartbeat(true);
        $this->refresh();
    }

    public function poll(): void
    {
        $this->heartbeat();
        $this->refresh();
    }

    public function clickCenter(): void
    {
        $prevPresent = $this->presentCount;   // count as the user last saw it

        DB::table('wheel_votes')->upsert(
            ['user_id' => auth()->id(), 'ready' => true, 'heartbeat_at' => now()],
            ['user_id'],
            ['ready', 'heartbeat_at']
        );

        $this->refresh();

        $allReady    = $this->readyCount >= $this->presentCount && $this->presentCount >= 2;
        $someoneLeft = $this->presentCount < $prevPresent;   // count dropped since last seen

        // Spin only on a deliberate click at a stable full room — never as a side
        // effect of someone leaving. The first click after a leave just settles the
        // now-smaller count; a second, informed click then spins.
        if ($allReady && !$someoneLeft) {
            $this->doSpin();
        }
    }

    private function heartbeat(bool $resetReady = false): void
    {
        DB::table('wheel_votes')->upsert(
            ['user_id' => auth()->id(), 'ready' => false, 'heartbeat_at' => now()],
            ['user_id'],
            $resetReady ? ['ready', 'heartbeat_at'] : ['heartbeat_at']
        );
    }

    private function refresh(): void
    {
        $cutoff = now()->subSeconds(12);

        $votes = DB::table('wheel_votes')
            ->join('users', 'users.id', '=', 'wheel_votes.user_id')
            ->where('wheel_votes.heartbeat_at', '>=', $cutoff)
            ->select('users.id', 'users.name', 'users.profile_picture', 'wheel_votes.ready')
            ->get();

        $this->presentCount = $votes->count();
        $this->readyCount   = $votes->where('ready', true)->count();
        $this->iAmReady     = $votes->where('id', auth()->id())->where('ready', true)->isNotEmpty();
        $this->presentUsers   = $votes->map(fn ($u) => [
            'name'    => $u->name,
            'initials' => collect(explode(' ', $u->name))->take(2)->map(fn ($w) => mb_substr($w, 0, 1))->implode(''),
            'picture' => $u->profile_picture,
            'ready'   => (bool) $u->ready,
        ])->values()->all();
        $this->presentUserIds = $votes->pluck('id')->all();

        $now    = now();
        $recent = WheelDraw::with('movie')->latest('drawn_at')->first();

        $active = $recent
            && $recent->movie
            && $recent->drawn_at
            && $recent->drawn_at->gt($now->copy()->subMinutes(self::RESULT_WINDOW_MIN));

        // The sidebar list always reflects the current watchlist, grouped by user,
        // even during the result window when the wheel shows the stored draw.
        $items = $this->buildWatchlistItems();
        $this->watchlistList = $this->watchlistListFrom($items);

        if ($active) {
            $this->result = [
                'id'     => $recent->movie->id,
                'title'  => $recent->movie->title,
                'slug'   => $recent->movie->slug,
                'poster' => $recent->movie->poster,
            ];

            // Show the wheel exactly as it was at spin time so the pointer lands on the winner.
            $this->segments    = !empty($recent->segments) ? $recent->segments : $this->computeLiveSegments($items);
            $this->drawId      = $recent->id;
            $this->targetAngle = $recent->target_angle !== null ? (float) $recent->target_angle : null;

            $fresh = $recent->drawn_at->gt($now->copy()->subSeconds(self::SPIN_FRESH_SEC));
            $willAnimate = $fresh
                && $this->targetAngle !== null
                && $this->lastAnimatedDrawId !== $recent->id;

            // Late arrivals (draw no longer fresh) see the wheel already resolved on the winner.
            if ($this->targetAngle !== null && !$fresh) {
                $this->initRotation   = fmod(360 - $this->targetAngle, 360);
                $this->initShowResult = true;
            } else {
                $this->initRotation   = 0.0;
                $this->initShowResult = false;
            }

            if ($willAnimate) {
                $this->lastAnimatedDrawId = $recent->id;
                $this->dispatch('wheel-spin', targetAngle: $this->targetAngle, drawId: $recent->id);
            }
        } else {
            $this->result         = null;
            $this->segments       = $this->computeLiveSegments($items);
            $this->drawId         = null;
            $this->targetAngle    = null;
            $this->initRotation   = 0.0;
            $this->initShowResult = false;
        }
    }

    private function doSpin(): void
    {
        $live = $this->computeLiveSegments($this->buildWatchlistItems());
        if (empty($live)) return;

        $movie = $this->weightedPick($live);
        if (!$movie) return;

        $angle = $this->angleForMovie($live, $movie->id);

        $draw = WheelDraw::create([
            'movie_id'     => $movie->id,
            'drawn_at'     => now(),
            'target_angle' => $angle,
            'segments'     => $live,
        ]);

        $movie->update(['status' => 'watching', 'watched_at' => now()->toDateString()]);
        DB::table('wheel_votes')->update(['ready' => false]);

        // Update fairness weights — only for users present during this spin.
        $winnerId = $movie->added_by;
        DB::table('wheel_votes')
            ->whereIn('user_id', $this->presentUserIds)
            ->where('user_id', '!=', $winnerId)
            ->increment('spins_since_last_win');
        if ($winnerId) {
            DB::table('wheel_votes')
                ->where('user_id', $winnerId)
                ->update(['spins_since_last_win' => 0]);
        }

        // Broadcast so every other open wheel spins at the same instant.
        // Silently ignore failures (e.g. clock skew) — polling fallback covers it.
        try {
            broadcast(new WheelSpun($draw->id, (float) $angle));
        } catch (\Throwable) {}

        // refresh() picks up the brand-new draw and animates it for the clicker right away.
        $this->refresh();
    }

    /**
     * A movie was drawn on another client. Re-sync — refresh() detects the fresh
     * draw and dispatches the spin animation to this browser.
     */
    #[On('echo:wheel,.WheelSpun')]
    public function onWheelSpun(): void
    {
        $this->refresh();
    }

    private function weightedPick(array $segments): ?Movie
    {
        if (empty($segments)) return null;

        $pool = [];
        foreach ($segments as $seg) {
            $slots = max(1, (int) round($seg['weight'] * 100));
            for ($i = 0; $i < $slots; $i++) {
                $pool[] = $seg['movie_id'];
            }
        }

        $picked = $pool[random_int(0, count($pool) - 1)];
        return Movie::find($picked);
    }

    /**
     * Fetch the present users' watchlist movies grouped by user (added_by, then
     * insertion order) with a stable per-movie color. This grouped order drives
     * the sidebar and the colors; the wheel reshuffles a copy of these items.
     */
    private function buildWatchlistItems(): array
    {
        $watchlist = Movie::where('status', 'watchlist')
            ->when(!empty($this->presentUserIds), fn ($q) => $q->whereIn('added_by', $this->presentUserIds))
            ->with('addedBy')
            ->orderBy('added_by')
            ->orderBy('id')
            ->get();

        if ($watchlist->isEmpty()) {
            return [];
        }

        // Weights are stored per-user in wheel_votes and only change while the user is present.
        $spinsSinceByUser = DB::table('wheel_votes')
            ->whereIn('user_id', $this->presentUserIds)
            ->pluck('spins_since_last_win', 'user_id')
            ->all();

        $colorIdx = 0;
        $items    = [];

        foreach ($watchlist as $movie) {
            $uid = $movie->added_by ?? 0;

            $spinsSince = $spinsSinceByUser[$uid] ?? 0;
            $weight = min(log($spinsSince + 1, 2) + 1, exp($spinsSince / 10));

            $items[] = [
                'movie_id'  => $movie->id,
                'title'     => $movie->title,
                'user_name' => $movie->addedBy?->name ?? '?',
                'weight'    => $weight,
                'color'     => $this->palette[$colorIdx++ % count($this->palette)],
            ];
        }

        return $items;
    }

    /**
     * The sidebar "Filmes na watchlist" rows — grouped by user, colors matching
     * each movie's slice on the wheel.
     */
    private function watchlistListFrom(array $items): array
    {
        return array_map(fn ($i) => [
            'title'     => $i['title'],
            'user_name' => $i['user_name'],
            'color'     => $i['color'],
        ], $items);
    }

    /**
     * Build the wheel segments from the shared items in a stable, user-independent
     * pseudo-random order (sorted by a hash of the movie id), so a user's movies
     * are scattered around the wheel instead of clustered, while staying stable
     * across the 3s polls.
     */
    private function computeLiveSegments(array $items): array
    {
        if (empty($items)) {
            return [];
        }

        usort($items, fn ($a, $b) => strcmp(md5((string) $a['movie_id']), md5((string) $b['movie_id'])));

        $totalWeight  = array_sum(array_column($items, 'weight'));
        $currentAngle = 0;
        $segments     = [];

        foreach ($items as $item) {
            $span       = min(($item['weight'] / $totalWeight) * 360, 359.9);
            $startAngle = $currentAngle;
            $endAngle   = $currentAngle + $span;
            $midAngle   = $currentAngle + $span / 2;

            // Split title into up to 2 lines for radial display
            $words = explode(' ', $item['title']);
            if (count($words) === 1 || mb_strlen($item['title']) <= 11) {
                $titleLines = [mb_strlen($item['title']) > 12 ? mb_substr($item['title'], 0, 11) . '…' : $item['title']];
            } else {
                $mid = (int) ceil(count($words) / 2);
                $l1  = implode(' ', array_slice($words, 0, $mid));
                $l2  = implode(' ', array_slice($words, $mid));
                $titleLines = [
                    mb_strlen($l1) > 12 ? mb_substr($l1, 0, 11) . '…' : $l1,
                    mb_strlen($l2) > 12 ? mb_substr($l2, 0, 11) . '…' : $l2,
                ];
            }

            $segments[] = [
                'movie_id'    => $item['movie_id'],
                'title'       => $item['title'],
                'user_name'   => $item['user_name'],
                'weight'      => $item['weight'],
                'color'       => $item['color'],
                'centerAngle' => $midAngle,
                'path'        => $this->sectorPath(200, 200, 180, 65, $startAngle, $endAngle),
                'textX'       => round(200 + 122 * sin(deg2rad($midAngle)), 2),
                'textY'       => round(200 - 122 * cos(deg2rad($midAngle)), 2),
                'titleLines'  => $titleLines,
                'fontSize'    => $span > 40 ? 11 : ($span > 22 ? 10 : 9),
                'showText'    => $span > 12,
            ];

            $currentAngle = $endAngle;
        }

        return $segments;
    }

    private function angleForMovie(array $segments, int $movieId): float
    {
        foreach ($segments as $seg) {
            if ($seg['movie_id'] === $movieId) {
                return $seg['centerAngle'];
            }
        }
        return 0;
    }

    private function sectorPath(float $cx, float $cy, float $R, float $r, float $startDeg, float $endDeg): string
    {
        $s = deg2rad($startDeg);
        $e = deg2rad($endDeg);

        $x1 = $cx + $R * sin($s);  $y1 = $cy - $R * cos($s);
        $x2 = $cx + $R * sin($e);  $y2 = $cy - $R * cos($e);
        $x3 = $cx + $r * sin($e);  $y3 = $cy - $r * cos($e);
        $x4 = $cx + $r * sin($s);  $y4 = $cy - $r * cos($s);

        $large = ($endDeg - $startDeg) > 180 ? 1 : 0;

        return sprintf(
            'M %.2f %.2f A %.2f %.2f 0 %d 1 %.2f %.2f L %.2f %.2f A %.2f %.2f 0 %d 0 %.2f %.2f Z',
            $x1, $y1, $R, $R, $large, $x2, $y2,
            $x3, $y3, $r, $r, $large, $x4, $y4
        );
    }

    public function render()
    {
        return view('livewire.wheel');
    }
}
