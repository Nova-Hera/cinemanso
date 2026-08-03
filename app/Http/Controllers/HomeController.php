<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Movie;
use App\Models\User;

class HomeController extends Controller
{
    public function index()
    {
        $recent = session('recent_items', []);

        $recentMovieIds = array_column(array_filter($recent, fn($i) => $i['type'] === 'movie'), 'id');
        $recentUserIds  = array_column(array_filter($recent, fn($i) => $i['type'] === 'user'),  'id');

        $moviesMap = Movie::whereIn('id', $recentMovieIds)->get()->keyBy('id');
        $usersMap  = User::whereIn('id',  $recentUserIds)->get()->keyBy('id');

        $items = [];
        foreach ($recent as $entry) {
            if ($entry['type'] === 'movie' && isset($moviesMap[$entry['id']])) {
                $items[] = ['type' => 'movie', 'model' => $moviesMap[$entry['id']]];
            } elseif ($entry['type'] === 'user' && isset($usersMap[$entry['id']])) {
                $items[] = ['type' => 'user', 'model' => $usersMap[$entry['id']]];
            }
        }

        $movieQuery = Movie::query();
        if (!empty($recentMovieIds)) {
            $movieQuery->whereNotIn('id', $recentMovieIds);
        }

        $sortedMovies = $movieQuery->get()->sort(function ($a, $b) {
            $aWatched = $a->watched_at !== null;
            $bWatched = $b->watched_at !== null;

            if ($aWatched !== $bWatched) {
                return $aWatched ? -1 : 1;
            }

            if ($aWatched && ! $a->watched_at->eq($b->watched_at)) {
                return $b->watched_at->getTimestamp() <=> $a->watched_at->getTimestamp();
            }

            return strnatcmp($a->sort_title, $b->sort_title);
        });

        foreach ($sortedMovies as $movie) {
            $items[] = ['type' => 'movie', 'model' => $movie];
        }

        $userQuery = User::orderBy('username', 'asc');
        if (!empty($recentUserIds)) {
            $userQuery->whereNotIn('id', $recentUserIds);
        }
        foreach ($userQuery->get() as $user) {
            $items[] = ['type' => 'user', 'model' => $user];
        }

        return view('welcome', compact('items'));
    }
}
