<?php

use App\Models\Movie;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('movies', function (Blueprint $table) {
            $table->json('genres')->nullable()->after('genre');
        });

        $canonical      = Movie::GENRES;
        $canonicalLower = array_map('mb_strtolower', $canonical);

        DB::table('movies')->whereNotNull('genre')->orderBy('id')->each(function ($row) use ($canonical, $canonicalLower) {
            $i      = array_search(mb_strtolower(trim($row->genre)), $canonicalLower);
            $genres = $i !== false ? [$canonical[$i]] : [];
            DB::table('movies')->where('id', $row->id)->update(['genres' => json_encode($genres)]);
        });

        Schema::table('movies', function (Blueprint $table) {
            $table->dropColumn('genre');
        });
    }

    public function down(): void
    {
        Schema::table('movies', function (Blueprint $table) {
            $table->string('genre')->nullable()->after('genres');
        });

        DB::table('movies')->whereNotNull('genres')->orderBy('id')->each(function ($row) {
            $genres = json_decode($row->genres, true) ?? [];
            DB::table('movies')->where('id', $row->id)->update(['genre' => $genres[0] ?? null]);
        });

        Schema::table('movies', function (Blueprint $table) {
            $table->dropColumn('genres');
        });
    }
};
