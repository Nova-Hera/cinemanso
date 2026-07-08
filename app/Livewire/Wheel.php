<?php

namespace App\Livewire;

use App\Models\Movie;
use App\Models\WheelDraw;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app', ['title' => 'Roleta'])]
class Wheel extends Component
{
    public array  $segments       = [];
    public int    $presentCount   = 0;
    public int    $readyCount      = 0;
    public bool   $iAmReady        = false;
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
        DB::table('wheel_votes')->upsert(
            ['user_id' => auth()->id(), 'ready' => true, 'heartbeat_at' => now()],
            ['user_id'],
            ['ready', 'heartbeat_at']
        );

        $this->refresh();

        if ($this->readyCount >= 2) {
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

        $this->presentCount = DB::table('wheel_votes')
            ->where('heartbeat_at', '>=', $cutoff)->count();

        $this->readyCount = DB::table('wheel_votes')
            ->where('heartbeat_at', '>=', $cutoff)
            ->where('ready', true)->count();

        $this->iAmReady = DB::table('wheel_votes')
            ->where('user_id', auth()->id())
            ->where('ready', true)
            ->where('heartbeat_at', '>=', $cutoff)
            ->exists();

        $now    = now();
        $recent = WheelDraw::with('movie')->latest('drawn_at')->first();

        $active = $recent
            && $recent->movie
            && $recent->drawn_at
            && $recent->drawn_at->gt($now->copy()->subMinutes(self::RESULT_WINDOW_MIN));

        if ($active) {
            $this->result = [
                'id'     => $recent->movie->id,
                'title'  => $recent->movie->title,
                'slug'   => $recent->movie->slug,
                'poster' => $recent->movie->poster,
            ];

            // Show the wheel exactly as it was at spin time so the pointer lands on the winner.
            $this->segments    = !empty($recent->segments) ? $recent->segments : $this->computeLiveSegments();
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
                $this->dispatch('wheel-spin', targetAngle: $this->targetAngle);
            }
        } else {
            $this->result         = null;
            $this->segments       = $this->computeLiveSegments();
            $this->drawId         = null;
            $this->targetAngle    = null;
            $this->initRotation   = 0.0;
            $this->initShowResult = false;
        }
    }

    private function doSpin(): void
    {
        $live = $this->computeLiveSegments();
        if (empty($live)) return;

        $movie = $this->weightedPick($live);
        if (!$movie) return;

        $angle = $this->angleForMovie($live, $movie->id);

        WheelDraw::create([
            'movie_id'     => $movie->id,
            'drawn_at'     => now(),
            'target_angle' => $angle,
            'segments'     => $live,
        ]);

        $movie->update(['status' => 'watching', 'watched_at' => now()->toDateString()]);
        DB::table('wheel_votes')->update(['ready' => false]);

        // refresh() picks up the brand-new draw as the active spin and dispatches the animation.
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

    private function computeLiveSegments(): array
    {
        $watchlist = Movie::where('status', 'watchlist')
            ->with('addedBy')
            ->get();

        if ($watchlist->isEmpty()) {
            return [];
        }

        $totalDraws = WheelDraw::count();

        $userIds = $watchlist->pluck('added_by')->filter()->unique()->values()->toArray();
        $lastDrawByUser = [];
        if (!empty($userIds)) {
            $rows = DB::table('wheel_draws')
                ->join('movies', 'movies.id', '=', 'wheel_draws.movie_id')
                ->whereIn('movies.added_by', $userIds)
                ->selectRaw('movies.added_by as user_id, MAX(wheel_draws.drawn_at) as last_drawn_at')
                ->groupBy('movies.added_by')
                ->get();
            foreach ($rows as $row) {
                $lastDrawByUser[$row->user_id] = $row->last_drawn_at;
            }
        }

        $spinsSinceByUser = [];
        foreach ($userIds as $uid) {
            if (isset($lastDrawByUser[$uid])) {
                $spinsSinceByUser[$uid] = WheelDraw::where('drawn_at', '>', $lastDrawByUser[$uid])->count();
            } else {
                $spinsSinceByUser[$uid] = $totalDraws;
            }
        }

        $colorIdx = 0;
        $items    = [];

        foreach ($watchlist as $movie) {
            $uid = $movie->added_by ?? 0;

            $spinsSince = $uid ? ($spinsSinceByUser[$uid] ?? $totalDraws) : 0;
            $weight = $uid
                ? min(log($spinsSince + 1, 2) + 1, exp($spinsSince / 10))
                : 1.0;

            $items[] = [
                'movie_id'  => $movie->id,
                'title'     => $movie->title,
                'user_name' => $movie->addedBy?->name ?? '?',
                'weight'    => $weight,
                'color'     => $this->palette[$colorIdx++ % count($this->palette)],
            ];
        }

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
