<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Movie extends Model
{

    protected $fillable = [
        'title',
        'image',
        'release_date',
        'average_rating',
        'modal_rating',
        'median_rating',
        'total_ratings',
    ];

    public function users()
    {
        return $this->belongsToMany(User::class)
            ->withPivot('rating', 'review')
            ->withTimestamps();
    }
}
