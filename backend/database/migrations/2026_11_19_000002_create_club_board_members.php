<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('club_board_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained()->cascadeOnDelete();
            $table->foreignId('player_id')->constrained('player_profiles')->cascadeOnDelete();
            $table->string('title', 120);
            $table->timestamps();
            $table->unique(['club_id', 'player_id']);
        });
    }

    public function down(): void { Schema::dropIfExists('club_board_members'); }
};
