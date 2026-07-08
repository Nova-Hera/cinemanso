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
    public array  $segments     = [];
    public int    $presentCount = 0;
    public int    $readyCount   = 0;
    public bool   $iAmReady     = false;
    public ?array $result       = null;

    private array $palette = [
        '#facc15', '#f97316', '#6366f1',
        '#ec4899', '#06b6d4', '#f59e0b',
        '#8b5cf6', '#ef4444',
    ];

    public function mount(): void
    {
        $this->heartbeat();
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

    private function heartbeat(): void
    {
        DB::table('wheel_votes')->upsert(
            ['user_id' => auth()->id(), 'ready' => false, 'heartbeat_at' => now()],
            ['user_id'],
            ['heartbeat_at']
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

        $this->loadSegments();
    }

    private function doSpin(): void
    {
        $movie = $this->weightedPick();
        if (!$movie) return;

        WheelDraw::create(['movie_id' => $movie->id]);
        $movie->update(['status' => 'watching', 'watched_at' => now()->toDateString()]);
        DB::table('wheel_votes')->update(['ready' => false]);

        $this->result = [
            'id'     => $movie->id,
            'title'  => $movie->title,
            'slug'   => $movie->slug,
            'poster' => $movie->poster,
        ];

        $angle = $this->angleForMovie($movie->id);
        $this->refresh();
        $this->dispatch('wheel-spin', targetAngle: $angle);
    }

    private function weightedPick(): ?Movie
    {
        if (empty($this->segments)) return null;

        $pool = [];
        foreach ($this->segments as $seg) {
            $slots = max(1, (int) round($seg['weight'] * 100));
            for ($i = 0; $i < $slots; $i++) {
                $pool[] = $seg['movie_id'];
            }
        }

        $picked = $pool[random_int(0, count($pool) - 1)];
        return Movie::find($picked);
    }

    private function loadSegments(): void
    {
        $watchlist = Movie::where('status', 'watchlist')
            ->with('addedBy')
            ->get();

        if ($watchlist->isEmpty()) {
            $this->segments = [];
            return;
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

        $this->segments = $segments;
    }

    private function angleForMovie(int $movieId): float
    {
        foreach ($this->segments as $seg) {
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
