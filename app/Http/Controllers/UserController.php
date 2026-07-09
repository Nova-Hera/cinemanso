<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;


class UserController extends Controller
{
    public function show(User $user) {        
    $user->load('reviews.movie');

    $ratings = $user->reviews->pluck('rating');
    $count = $ratings->count();
    $media = $count ? round($ratings->avg(), 2) : null;
    $sorted = $ratings->sort()->values();

    if($count===0) {
        $mediana = null;
    } elseif($count%2 === 0) {
        $mediana = round(($sorted[$count/2 - 1] + $sorted[$count/2])/2, 2);
    } else {
        $mediana = round($sorted[intdiv($count, 2)], 2);
    }

    $moda = null;
    if ($count > 0) {
        $counts = array_count_values($ratings->toArray());
        $max = max($counts);
        $moda = array_keys($counts, $max);
        $moda = count($moda) >= 4 ? 'Multimodal' : implode('; ', $moda);
    }

    $recent = session()->get('recent_items', []);
    $recent = array_values(array_filter($recent, fn ($i) => !($i['type'] === 'user' && $i['id'] === $user->id)));
    array_unshift($recent, ['type' => 'user', 'id' => $user->id]);
    session()->put('recent_items', array_slice($recent, 0, 15));

    return view('users.show', compact('user', 'media', 'mediana', 'moda'));
    }

}
