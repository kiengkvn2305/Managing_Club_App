<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::table('team_players', fn (Blueprint $table) => $table->string('position', 5)->nullable());
        Schema::getConnection()->statement('update team_players set position = (select position from player_profiles where player_profiles.id = team_players.player_id)');
        Schema::create('finance_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->enum('type', ['income', 'expense']);
            $table->unsignedBigInteger('amount');
            $table->date('occurred_on');
            $table->string('description', 255);
            $table->timestamps();
            $table->index(['club_id', 'occurred_on']);
        });
    }
    public function down(): void {
        Schema::dropIfExists('finance_transactions');
        Schema::table('team_players', fn (Blueprint $table) => $table->dropColumn('position'));
    }
};
