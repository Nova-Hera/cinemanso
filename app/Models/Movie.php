<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

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
        'imdb_id',
    ];

    protected $casts = [
        'release_date' => 'date',
        'watched_at'   => 'date',
        'genres'       => 'array',
        'streamings' => 'array',
    ];

    /**
     * Chave de ordenação alfabética que ignora acentos e caixa.
     * Ex.: "Ávatar" -> "avatar", "Ação" -> "acao".
     */
    public function getSortTitleAttribute(): string
    {
        return Str::lower(Str::ascii($this->title));
    }

    public function reviews()
    {
        return $this->hasMany(Reviews::class);
    }

    public function addedBy()
    {
        return $this->belongsTo(User::class, 'added_by');
    }
}
