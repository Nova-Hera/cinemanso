<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Movie;
use App\Models\Reviews;

class ReviewController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'movie_id' => 'required|exists:movies,id',
            'rating'   => 'required|numeric|min:0|max:10',
            'content'  => 'required|string',
        ]);

        $movie = Movie::findOrFail($request->movie_id);

        Reviews::create([
            'user_id'  => auth()->id(),
            'movie_id' => $movie->id,
            'content'  => $request->content,
            'rating'   => $request->rating,
            'status'   => 'published',
        ]);

        $movie->update(['rating' => $movie->reviews()->avg('rating')]);

        return redirect()->route('movies.show', $movie->slug);
    }

    public function edit(Reviews $review)
    {
        if ($review->user_id !== auth()->id()) {
            abort(403);
        }

        $review->load('movie');

        return view('reviews.edit', compact('review'));
    }

    public function update(Request $request, Reviews $review)
    {
        if ($review->user_id !== auth()->id()) {
            abort(403);
        }

        $request->validate([
            'rating'  => 'required|numeric|min:0|max:10',
            'content' => 'required|string',
        ]);

        $review->update([
            'rating'  => $request->rating,
            'content' => $request->content,
        ]);

        $movie = $review->movie;
        $movie->update(['rating' => $movie->reviews()->avg('rating')]);

        return redirect()->route('movies.show', $movie->slug);
    }
}
