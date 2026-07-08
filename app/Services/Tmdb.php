<?php

namespace App\Services;

use App\Models\Movie;
use Illuminate\Support\Facades\Http;

class Tmdb
{
    private const GENRE_MAP = [
        28    => 'Ação',
        12    => 'Aventura',
        16    => 'Animação',
        35    => 'Comédia',
        80    => 'Crime / Policial',
        99    => 'Documentário',
        18    => 'Drama',
        14    => 'Fantasia',
        27    => 'Terror / Horror',
        10402 => 'Musical',
        9648  => 'Suspense / Thriller',
        10749 => 'Romance',
        878   => 'Ficção Científica',
        53    => 'Suspense / Thriller',
        10752 => 'Guerra',
        37    => 'Western (Faroeste)',
    ];

    private string $key;
    private string $base;
    private string $imageBase;

    public function __construct()
    {
        $this->key       = config('services.tmdb.key', '');
        $this->base      = config('services.tmdb.base_url', 'https://api.themoviedb.org/3');
        $this->imageBase = config('services.tmdb.image_base', 'https://image.tmdb.org/t/p/w500');
    }

    public function configured(): bool
    {
        return $this->key !== '';
    }

    public function search(string $query): array
    {
        $response = Http::timeout(8)->get("{$this->base}/search/movie", [
            'api_key'        => $this->key,
            'query'          => $query,
            'language'       => 'pt-BR',
            'include_adult'  => 'false',
        ]);

        if (! $response->ok()) {
            return [];
        }

        $results = $response->json('results', []);

        return collect($results)
            ->take(5)
            ->map(fn ($r) => [
                'id'    => $r['id'],
                'title' => $r['title'] ?? $r['original_title'] ?? '',
                'year'  => isset($r['release_date']) ? substr($r['release_date'], 0, 4) : '?',
                'thumb' => $r['poster_path'] ? $this->imageBase . $r['poster_path'] : null,
            ])
            ->values()
            ->all();
    }

    public function details(int $id): array
    {
        $response = Http::timeout(8)->get("{$this->base}/movie/{$id}", [
            'api_key'          => $this->key,
            'language'         => 'pt-BR',
            'append_to_response' => 'credits',
        ]);

        if (! $response->ok()) {
            return [];
        }

        $data = $response->json();

        $director = collect($data['credits']['crew'] ?? [])
            ->firstWhere('job', 'Director');

        $genres = collect($data['genres'] ?? [])
            ->map(fn ($g) => self::GENRE_MAP[$g['id']] ?? null)
            ->filter()
            ->unique()
            ->values()
            ->all();

        return [
            'title'       => $data['title'] ?? '',
            'director'    => $director['name'] ?? '',
            'releaseDate' => $data['release_date'] ?? '',
            'description' => $data['overview'] ?? '',
            'posterUrl'   => $data['poster_path'] ? $this->imageBase . $data['poster_path'] : '',
            'genres'      => $genres,
        ];
    }
}
