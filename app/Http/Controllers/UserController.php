<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;


class UserController extends Controller
{
    public function show(User $user) {
        $user->load('reviews.movie', 'addedMovies');

        $reviewStats = $this->stats($user->reviews->pluck('rating'));

        $addedMovies = $user->addedMovies->sortByDesc('created_at')->values();
        $movieStats  = $this->stats(
            $addedMovies->pluck('rating')->filter(fn ($r) => $r !== null)->values()
        );

        $recent = session()->get('recent_items', []);
        $recent = array_values(array_filter($recent, fn ($i) => !($i['type'] === 'user' && $i['id'] === $user->id)));
        array_unshift($recent, ['type' => 'user', 'id' => $user->id]);
        session()->put('recent_items', array_slice($recent, 0, 15));

        return view('users.show', compact('user', 'reviewStats', 'movieStats', 'addedMovies'));
    }

    private function stats(\Illuminate\Support\Collection $ratings): array
    {
        $count = $ratings->count();
        if ($count === 0) {
            return ['media' => null, 'mediana' => null, 'moda' => null];
        }

        $media   = round($ratings->avg(), 2);
        $sorted  = $ratings->sort()->values();
        $mediana = $count % 2 === 0
            ? round(($sorted[$count / 2 - 1] + $sorted[$count / 2]) / 2, 2)
            : round($sorted[intdiv($count, 2)], 2);

        $counts = array_count_values($ratings->map(fn ($r) => (string) $r)->toArray());
        $max    = max($counts);
        $moda   = array_keys($counts, $max);
        $moda   = count($moda) >= 4
            ? 'Multimodal'
            : implode('; ', array_map(
                fn ($v) => rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.'),
                $moda
            ));

        return compact('media', 'mediana', 'moda');
    }

}
