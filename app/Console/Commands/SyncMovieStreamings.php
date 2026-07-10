<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Movie;
use App\Services\Tmdb;

class SyncMovieStreamings extends Command
{
    // O comando que você vai digitar no terminal
    protected $signature = 'movies:sync-streamings';

    // A descrição do que o comando faz
    protected $description = 'Busca e atualiza os dados de streaming dos filmes antigos baseado no título';

    public function handle()
    {
        $tmdb = app(Tmdb::class);

        if (!$tmdb->configured()) {
            $this->error('A chave da API do TMDB não está configurada!');
            return Command::FAILURE;
        }

        // Pega todos os filmes onde o campo streamings está nulo ou vazio
        $movies = Movie::all()->filter(function ($movie) {
            return empty($movie->streamings) ||
                   (isset($movie->streamings['BR']) && empty($movie->streamings['BR']) && empty($movie->streamings['US']));
        });

        if ($movies->isEmpty()) {
            $this->info('Todos os seus filmes já estão com os streamings atualizados! 🎉');
            return Command::SUCCESS;
        }

        $this->info('Iniciando a sincronização de ' . $movies->count() . ' filmes...');

        foreach ($movies as $movie) {
            $this->line("--------------------------------------------------");
            $this->info("Buscando: {$movie->title}");

            // 1. Pesquisa no TMDB usando o título gravado no banco
            $results = $tmdb->search($movie->title);

            if (empty($results)) {
                $this->warn("⚠️ Nenhum resultado encontrado no TMDB para este título.");
                continue;
            }

            // 2. Pega o primeiro resultado retornado (geralmente o mais correto)
            $firstMatch = $results[0];
            $this->line("Encontrado: " . $firstMatch['title'] . " (" . $movie->title . ")");

            // 3. Pega os detalhes completos (onde vêm os streamings)
            $details = $tmdb->details($firstMatch['id'], $firstMatch['type']);

            if (!empty($details) && isset($details['streamings'])) {
                // 4. Salva no banco de dados
                $movie->update([
                    'streamings' => $details['streamings']
                ]);
                $this->info("✅ Streamings atualizados com sucesso!");
            } else {
                $this->warn("❌ Não foi possível extrair os streamings para este filme.");
            }

            // Pausa de 0.2 segundos para não sobrecarregar a API do TMDB
            usleep(200000);
        }

        $this->line("--------------------------------------------------");
        $this->info('🚀 Processo concluído com sucesso!');
        return Command::SUCCESS;
    }
}