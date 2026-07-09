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
        Schema::table('wheel_votes', function (Blueprint $table) {
            $table->unsignedInteger('spins_since_last_win')->default(0)->after('ready');
        });
    }

    public function down(): void
    {
        Schema::table('wheel_votes', function (Blueprint $table) {
            $table->dropColumn('spins_since_last_win');
        });
    }
};
