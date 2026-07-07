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

        $movieQuery = Movie::orderByRaw('CASE WHEN watched_at IS NULL THEN 1 ELSE 0 END')
            ->orderBy('watched_at', 'desc')
            ->orderBy('title', 'asc');
        if (!empty($recentMovieIds)) {
            $movieQuery->whereNotIn('id', $recentMovieIds);
        }
        foreach ($movieQuery->get() as $movie) {
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
