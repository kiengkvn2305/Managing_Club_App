<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('club_matches', function (Blueprint $table) {
            $table->id(); $table->foreignId('club_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('team_id')->nullable(); $table->foreign(['club_id', 'team_id'])->references(['club_id', 'id'])->on('teams')->restrictOnDelete();
            $table->foreignId('friendly_invitation_id')->nullable()->constrained('friendly_invitations')->restrictOnDelete();
            $table->string('opponent', 120); $table->date('match_date'); $table->string('start_time', 5); $table->string('end_time', 5)->nullable();
            $table->string('location', 160)->nullable(); $table->string('match_type', 16); $table->string('status', 16);
            $table->unsignedSmallInteger('our_score')->nullable(); $table->unsignedSmallInteger('opponent_score')->nullable(); $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete(); $table->timestamps();
            $table->unique(['club_id', 'friendly_invitation_id']); $table->index(['club_id', 'match_date']);
        });
    }
    public function down(): void { Schema::dropIfExists('club_matches'); }
};
