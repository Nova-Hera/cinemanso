<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Movie extends Model
{
    protected $fillable = [
        'title',
        'slug',
        'description',
        'director',
        'poster',
        'release_date',
        'genre',
        'watched_at',
        'rating',
        'status',
        'added_by',
    ];

    protected $casts = [
        'release_date' => 'date',
        'watched_at' => 'date',
    ];

    public function reviews()
    {
        return $this->hasMany(Reviews::class);
    }

    public function addedBy()
    {
        return $this->belongsTo(User::class, 'added_by');
    }
}
