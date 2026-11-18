<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('match_cost_splits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('match_id');
            $table->foreign(['club_id', 'match_id'])->references(['club_id', 'id'])->on('club_matches')->restrictOnDelete();
            foreach (['stadium', 'referee', 'water', 'equipment', 'other', 'sponsorship', 'total'] as $name) $table->unsignedBigInteger($name);
            $table->string('method', 12);
            $table->string('payload_hash', 64);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['club_id', 'match_id']);
            $table->unique(['club_id', 'id']);
        });
        Schema::create('match_cost_receivables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('split_id');
            $table->unsignedBigInteger('player_id');
            $table->foreign(['club_id', 'split_id'])->references(['club_id', 'id'])->on('match_cost_splits')->restrictOnDelete();
            $table->foreign(['club_id', 'player_id'])->references(['club_id', 'id'])->on('player_profiles')->restrictOnDelete();
            $table->unsignedBigInteger('amount');
            $table->boolean('exempt')->default(false);
            $table->unsignedInteger('weight')->default(1);
            $table->timestamps();
            $table->unique(['split_id', 'player_id']);
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('match_cost_receivables');
        Schema::dropIfExists('match_cost_splits');
    }
};
