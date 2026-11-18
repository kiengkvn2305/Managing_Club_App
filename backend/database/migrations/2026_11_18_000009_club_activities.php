<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('club_celebrations', function (Blueprint $table) {
            $table->id(); $table->foreignId('club_id')->constrained()->restrictOnDelete();
            $table->string('title', 160); $table->date('event_date'); $table->text('message')->nullable(); $table->timestamps();
        });
        Schema::create('team_trainings', function (Blueprint $table) {
            $table->id(); $table->foreignId('club_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('team_id')->nullable(); $table->string('team_name', 120);
            $table->date('training_date'); $table->string('training_time', 5); $table->string('location', 160); $table->text('notes')->nullable(); $table->json('attendance'); $table->timestamps();
        });
        Schema::create('club_awards', function (Blueprint $table) {
            $table->id(); $table->foreignId('club_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('player_id')->nullable(); $table->string('player_name', 120);
            $table->unsignedSmallInteger('award_year'); $table->string('title', 160); $table->string('gift', 255)->nullable(); $table->text('notes')->nullable(); $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('club_awards'); Schema::dropIfExists('team_trainings'); Schema::dropIfExists('club_celebrations'); }
};
