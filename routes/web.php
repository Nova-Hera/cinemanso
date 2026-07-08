<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;
use \App\Http\Controllers;

Route::get('/', [\App\Http\Controllers\HomeController::class, 'index'])->name('home');

Route::get('movies', function () {
    $status = request('status');
    $sort   = request('sort', 'title');

    $query = \App\Models\Movie::with('reviews');
    if (in_array($status, ['watchlist', 'watching', 'watched'])) {
        $query->where('status', $status);
    }
    $movies = $query->get();

    $movies = match($sort) {
        'media' => $movies->sortByDesc(
            fn($m) => $m->reviews->avg('rating') ?? -1
        ),
        'mediana' => $movies->sortByDesc(function ($m) {
            $r = $m->reviews->pluck('rating')->sort()->values();
            if ($r->isEmpty()) return -1;
            $c = $r->count(); $mid = (int)($c / 2);
            return $c % 2 === 0 ? ($r[$mid - 1] + $r[$mid]) / 2 : $r[$mid];
        }),
        'moda' => $movies->sortByDesc(
            fn($m) => $m->reviews->isEmpty() ? -1
                : $m->reviews->pluck('rating')->countBy()->sortDesc()->keys()->first()
        ),
        default => $movies->sortBy('title'),
    };

    return view('movies.index', [
        'movies'        => $movies->values(),
        'currentStatus' => $status,
        'currentSort'   => $sort,
    ]);
})->name('movies.index');

Route::get('movies/{movie:slug}', [\App\Http\Controllers\MovieController::class, 'show'])->name('movies.show');
Route::patch('movies/{movie:slug}/status', [\App\Http\Controllers\MovieController::class, 'updateStatus'])->name('movies.updateStatus');

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::get('reviews/{movie:slug}/{user:username}', function ($movie, $username) {
    return view('reviews.movie.user.show', ['slug' => $movie, 'username' => $username]);
})->name('reviews.movie.user.show');

Route::get('user/{user:username}', [\App\Http\Controllers\UserController::class, 'show'])->name('users.show');

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', 'settings/profile');

    Route::post('reviews', [\App\Http\Controllers\ReviewController::class, 'store'])->name('reviews.store');
    Route::put('reviews/{review}', [\App\Http\Controllers\ReviewController::class, 'update'])->name('reviews.update');

    Volt::route('settings/profile', 'settings.profile')->name('settings.profile');
    Volt::route('settings/password', 'settings.password')->name('settings.password');
    Volt::route('settings/appearance', 'settings.appearance')->name('settings.appearance');
});

require __DIR__.'/auth.php';
