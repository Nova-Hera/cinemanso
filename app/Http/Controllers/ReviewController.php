<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
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
            'image'    => 'nullable|image|max:4096',
        ]);

        $movie = Movie::findOrFail($request->movie_id);

        Reviews::create([
            'user_id'    => auth()->id(),
            'movie_id'   => $movie->id,
            'content'    => $request->content,
            'image_path' => $request->hasFile('image') ? $request->file('image')->store('review-images', 'public') : null,
            'rating'     => $request->rating,
            'status'     => 'published',
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
            'image'   => 'nullable|image|max:4096',
        ]);

        $imagePath = $review->image_path;

        if ($request->hasFile('image')) {
            if ($imagePath) {
                Storage::disk('public')->delete($imagePath);
            }
            $imagePath = $request->file('image')->store('review-images', 'public');
        } elseif ($request->input('remove_image') === '1') {
            if ($imagePath) {
                Storage::disk('public')->delete($imagePath);
            }
            $imagePath = null;
        }

        $review->update([
            'rating'     => $request->rating,
            'content'    => $request->content,
            'image_path' => $imagePath,
        ]);

        $movie = $review->movie;
        $movie->update(['rating' => $movie->reviews()->avg('rating')]);

        return redirect()->route('movies.show', $movie->slug);
    }
}
