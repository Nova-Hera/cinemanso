<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
   public function up(): void
{
    Schema::table('movies', function (Blueprint $table) {
        $table->string('imdb_id')->nullable()->after('title');
    });
}

public function down(): void
{
    Schema::table('movies', function (Blueprint $table) {
        $table->dropColumn('imdb_id');
    });
}
};