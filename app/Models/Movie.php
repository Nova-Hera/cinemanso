<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Movie extends Model
{
    public const GENRES = [
        'Ação', 'Aventura', 'Comédia', 'Drama', 'Romance',
        'Terror / Horror', 'Suspense / Thriller', 'Ficção Científica',
        'Fantasia', 'Animação', 'Documentário', 'Musical',
        'Crime / Policial', 'Guerra', 'Western (Faroeste)',
    ];

    protected $fillable = [
        'title',
        'slug',
        'description',
        'director',
        'poster',
        'release_date',
        'genres',
        'watched_at',
        'rating',
        'status',
        'added_by',
        'streamings',
    ];

    protected $casts = [
        'release_date' => 'date',
        'watched_at'   => 'date',
        'genres'       => 'array',
        'streamings' => 'array',
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
