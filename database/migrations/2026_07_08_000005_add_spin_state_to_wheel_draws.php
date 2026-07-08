<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wheel_draws', function (Blueprint $table) {
            $table->float('target_angle')->nullable()->after('movie_id');
            $table->json('segments')->nullable()->after('target_angle');
        });
    }

    public function down(): void
    {
        Schema::table('wheel_draws', function (Blueprint $table) {
            $table->dropColumn(['target_angle', 'segments']);
        });
    }
};
