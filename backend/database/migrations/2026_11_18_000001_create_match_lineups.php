<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('match_lineups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('team_id');
            $table->string('opponent', 120);
            $table->date('match_date');
            $table->string('formation', 120)->nullable();
            $table->json('players');
            $table->timestamps();
            $table->foreign(['club_id', 'team_id'])->references(['club_id', 'id'])->on('teams')->restrictOnDelete();
        });
        Schema::table('teams', function (Blueprint $table) {
            $table->unsignedBigInteger('captain_id')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('teams', fn (Blueprint $table) => $table->dropColumn('captain_id'));
        Schema::dropIfExists('match_lineups');
    }
};
