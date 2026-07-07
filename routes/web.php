<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;
use \App\Http\Controllers;

Route::get('/', [\App\Http\Controllers\HomeController::class, 'index'])->name('home');

Route::get('movies', function () {
    return view('movies.index', ['movies' => \App\Models\Movie::orderBy('title')->get()]);
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
