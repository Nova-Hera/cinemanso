<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Movie;

class MovieController extends Controller
{
    public function show(Movie $movie) {
        $movie->load('reviews.user');

        $ratings = $movie->reviews->pluck('rating');
        $count   = $ratings->count();

        $media   = $movie->rating;

        $sorted = $ratings->sort()->values();
        if ($count === 0) {
            $mediana = null;
        } elseif ($count % 2 === 0) {
            $mediana = round(($sorted[$count / 2 - 1] + $sorted[$count / 2]) / 2, 1);
        } else {
            $mediana = round($sorted[intdiv($count, 2)], 1);
        }

        $moda = null;

        if ($count > 0) {
            $values = $ratings->toArray();
            $counts = array_count_values($values);
            $max = max($counts);

            $moda = array_keys($counts, $max);
            $moda = count($moda) >= 4 ? 'Multimodal' : implode('; ', $moda);

        }

        if ($movie->rating !== null) {
            $ranking = Movie::whereNotNull('rating')->where('rating', '>', $movie->rating)->count() + 1;

            $genreRankings = [];
            foreach ($movie->genres ?? [] as $g) {
                $genreRankings[$g] = Movie::whereNotNull('rating')
                    ->whereJsonContains('genres', $g)
                    ->where('rating', '>', $movie->rating)
                    ->count() + 1;
            }
        } else {
            $ranking       = null;
            $genreRankings = [];
        }

        $recent = session()->get('recent_items', []);
        $recent = array_values(array_filter($recent, fn ($i) => !($i['type'] === 'movie' && $i['id'] === $movie->id)));
        array_unshift($recent, ['type' => 'movie', 'id' => $movie->id]);
        session()->put('recent_items', array_slice($recent, 0, 15));

        $userReview = auth()->check() ? $movie->reviews->firstWhere('user_id', auth()->id()) : null;

        return view('movies.show', compact('movie', 'userReview', 'media', 'mediana', 'moda', 'ranking', 'genreRankings'));
    }

    public function updateStatus(Movie $movie, \Illuminate\Http\Request $request)
    {
        if(!auth()->check()) {
            abort(403);
        }
        $request->validate(['status' => 'required|in:watchlist,watching,watched']);
        $movie->update(['status' => $request->status]);
        return back();
    }
}
