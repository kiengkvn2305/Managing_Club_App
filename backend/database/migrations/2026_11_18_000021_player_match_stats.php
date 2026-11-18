<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('player_match_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('match_id');
            $table->unsignedBigInteger('player_id');
            $table->foreign(['club_id', 'match_id'])->references(['club_id', 'id'])->on('club_matches')->restrictOnDelete();
            $table->foreign(['club_id', 'player_id'])->references(['club_id', 'id'])->on('player_profiles')->restrictOnDelete();
            $table->unsignedSmallInteger('goals')->default(0);
            $table->unsignedSmallInteger('assists')->default(0);
            $table->unsignedTinyInteger('yellow_cards')->default(0);
            $table->unsignedTinyInteger('red_cards')->default(0);
            // Null means goalkeeper statistics were not recorded; zero means a clean sheet.
            $table->unsignedSmallInteger('goals_conceded')->nullable();
            $table->decimal('rating', 3, 1)->nullable();
            $table->timestamps();
            $table->unique(['match_id', 'player_id']);
            $table->index(['club_id', 'player_id']);
        });
    }

    public function down(): void { Schema::dropIfExists('player_match_stats'); }
};
