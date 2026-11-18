<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('board_dues_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained()->cascadeOnDelete();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('player_id')->constrained('player_profiles')->cascadeOnDelete();
            $table->date('effective_month');
            $table->date('ends_before')->nullable();
            $table->unsignedBigInteger('amount');
            $table->timestamps();
            $table->index(['club_id', 'team_id', 'player_id', 'effective_month']);
        });
    }

    public function down(): void { Schema::dropIfExists('board_dues_rates'); }
};
