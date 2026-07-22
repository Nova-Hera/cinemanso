<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Movie;
use App\Services\Tmdb;

class SyncMovieImdbIds extends Command
{
    protected $signature = 'movies:sync-imdb-ids';
    protected $description = 'Busca no TMDB e atualiza o imdb_id de TODOS os filmes pendentes';

    public function handle()
    {
        $tmdb = app(Tmdb::class);

        if (!$tmdb->configured()) {
            $this->error('A chave da API do TMDB não está configurada!');
            return Command::FAILURE;
        }

        // Pega APENAS os filmes sem imdb_id
        $query = Movie::where(function ($q) {
            $q->whereNull('imdb_id')->orWhere('imdb_id', '');
        });

        $total = $query->count();

        if ($total === 0) {
            $this->info('Todos os seus filmes já possuem o IMDb ID cadastrado! 🎉');
            return Command::SUCCESS;
        }

        $this->info("Iniciando busca para {$total} filmes restantes...");

        // Processa TODOS os filmes usando cursor para economizar memória
        foreach ($query->cursor() as $movie) {
            $this->line("--------------------------------------------------");
            $this->info("Buscando ({$movie->id}): {$movie->title}");

            $results = $tmdb->search($movie->title);

            if (empty($results)) {
                $this->warn("⚠️ Nenhum resultado encontrado no TMDB.");
                continue;
            }

            $firstMatch = $results[0];
            $details = $tmdb->details($firstMatch['id'], $firstMatch['type'] ?? 'movie');

            $imdbId = $details['imdb_id'] ?? $details['external_ids']['imdb_id'] ?? null;

            if ($imdbId) {
                // Atualiza sem disparar eventos desnecessários
                $movie->update(['imdb_id' => $imdbId]);
                $this->info("✅ IMDb ID salvo: {$imdbId}");
            } else {
                $this->warn("❌ Encontrado no TMDB, mas sem IMDb ID vinculado.");
            }

            // Pausa leve para respeitar o Rate Limit da API do TMDB
            usleep(250000); // 0.25 seg
        }

        $this->line("--------------------------------------------------");
        $this->info('🚀 Processo finalizado com sucesso!');
        return Command::SUCCESS;
    }
}
