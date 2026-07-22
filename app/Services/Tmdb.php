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
        // TV-specific genre IDs
        10759 => 'Ação',
        10765 => 'Ficção Científica',
        10768 => 'Guerra',
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
        $response = Http::timeout(8)->get("{$this->base}/search/multi", [
            'api_key'       => $this->key,
            'query'         => $query,
            'language'      => 'pt-BR',
            'include_adult' => 'false',
        ]);

        if (!$response->ok()) return [];

        return collect($response->json('results', []))
            ->filter(fn ($r) => in_array($r['media_type'] ?? '', ['movie', 'tv']))
            ->take(5)
            ->map(fn ($r) => [
                'id'    => $r['id'],
                'type'  => $r['media_type'],
                'title' => $r['title'] ?? $r['name'] ?? $r['original_title'] ?? $r['original_name'] ?? '',
                'year'  => substr($r['release_date'] ?? $r['first_air_date'] ?? '', 0, 4) ?: '?',
                'thumb' => ($r['poster_path'] ?? null) ? $this->imageBase . $r['poster_path'] : null,
            ])
            ->values()
            ->all();
    }

    public function details(int $id, string $type = 'movie'): array
    {
        $endpoint = $type === 'tv' ? "tv/{$id}" : "movie/{$id}";

        // ADICIONADO: 'watch/providers' incluído no append_to_response para economizar requisições
        $response = Http::timeout(8)->get("{$this->base}/{$endpoint}", [
            'api_key'            => $this->key,
            'language'           => 'pt-BR',
            'append_to_response' => 'credits,watch/providers',
        ]);

        if (!$response->ok()) return [];

        $data = $response->json();

        if ($type === 'tv') {
            $title   = $data['name'] ?? $data['original_name'] ?? '';
            $creator = $data['created_by'][0]['name'] ?? '';
            $date    = $data['first_air_date'] ?? '';
        } else {
            $title   = $data['title'] ?? $data['original_title'] ?? '';
            $creator = collect($data['credits']['crew'] ?? [])->firstWhere('job', 'Director')['name'] ?? '';
            $date    = $data['release_date'] ?? '';
        }

        $genres = collect($data['genres'] ?? [])
            ->map(fn ($g) => self::GENRE_MAP[$g['id']] ?? null)
            ->filter()
            ->unique()
            ->values()
            ->all();

        // ADICIONADO: Extração dos dados de streaming (BR e US)
        $providers = $data['watch/providers']['results'] ?? [];

        return [
            'title'       => $title,
            'director'    => $creator,
            'releaseDate' => $date,
            'description' => $data['overview'] ?? '',
            'posterUrl'   => ($data['poster_path'] ?? null) ? $this->imageBase . $data['poster_path'] : '',
            'genres'      => $genres,
            'streamings'  => [
                'BR' => $this->formatProviders($providers['BR'] ?? []),
                'US' => $this->formatProviders($providers['US'] ?? []),
            ],
            'imdb_id' => $data['imdb_id'] ?? null,
        ];
    }

    /**
     * ADICIONADO: Método auxiliar para formatar os streamings de assinatura (flatrate)
     */
    private function formatProviders(array $countryData): array
    {
        // Focamos em 'flatrate' que são os serviços inclusos na assinatura (Netflix, Disney+, Prime, etc)
        return collect($countryData['flatrate'] ?? [])
            ->map(fn ($provider) => [
                'name' => $provider['provider_name'],
                'logo' => ($provider['logo_path'] ?? null) ? "https://image.tmdb.org/t/p/original" . $provider['logo_path'] : null,
            ])
            ->all();
    }
}