<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('club_matches', fn (Blueprint $table) => $table->unique(['club_id', 'id']));
        Schema::create('match_attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('match_id');
            $table->unsignedBigInteger('player_id');
            $table->foreign(['club_id', 'match_id'])->references(['club_id', 'id'])->on('club_matches')->restrictOnDelete();
            $table->foreign(['club_id', 'player_id'])->references(['club_id', 'id'])->on('player_profiles')->restrictOnDelete();
            $table->string('rsvp', 12)->default('pending');
            $table->string('attendance', 12)->default('unmarked');
            $table->timestamps();
            $table->unique(['match_id', 'player_id']);
            $table->index(['club_id', 'match_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('match_attendances');
        Schema::table('club_matches', fn (Blueprint $table) => $table->dropUnique(['club_id', 'id']));
    }
};
