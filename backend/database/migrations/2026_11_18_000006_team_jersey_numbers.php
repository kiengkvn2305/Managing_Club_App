<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::table('team_players', fn (Blueprint $table) => $table->unsignedSmallInteger('jersey_number')->nullable());
        Schema::getConnection()->statement('update team_players set jersey_number = (select jersey_number from player_profiles where player_profiles.id = team_players.player_id)');
        Schema::getConnection()->statement('update player_profiles set current_jersey = null');
    }
    public function down(): void { Schema::table('team_players', fn (Blueprint $table) => $table->dropColumn('jersey_number')); }
};
