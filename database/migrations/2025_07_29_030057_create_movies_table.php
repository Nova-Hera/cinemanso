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
        Schema::create('movies', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->string('title');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->string('director')->nullable();
            $table->string('poster');
            $table->date('release_date')->nullable();
            $table->string('genre')->nullable();
            $table->date('watched_at')->nullable();
            $table->decimal('rating', 4, 2)->nullable();
            $table->string('status')->default('unwatched'); // watchlist, watching, watched
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('movies');
    }
};
