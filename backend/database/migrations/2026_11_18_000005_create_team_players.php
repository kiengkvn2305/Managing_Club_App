<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('team_players', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('club_id');
            $table->unsignedBigInteger('team_id');
            $table->foreignId('player_id')->constrained('player_profiles')->restrictOnDelete();
            $table->foreign(['club_id', 'team_id'])->references(['club_id', 'id'])->on('teams')->restrictOnDelete();
            $table->unique(['team_id', 'player_id']);
        });
        Schema::getConnection()->statement('insert into team_players (club_id, team_id, player_id) select club_id, team_id, id from player_profiles where team_id is not null');
    }
    public function down(): void { Schema::dropIfExists('team_players'); }
};
