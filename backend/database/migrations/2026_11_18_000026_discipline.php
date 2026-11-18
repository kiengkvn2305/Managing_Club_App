<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('discipline_cases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('player_id');
            $table->date('incident_date');
            $table->string('title', 200);
            $table->text('description');
            $table->string('status', 20)->default('open');
            $table->text('decision')->nullable();
            // Informational only: this does not create a receivable or payment.
            $table->decimal('fine_amount', 15, 2)->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['club_id', 'id']);
            $table->foreign(['club_id', 'player_id'])->references(['club_id', 'id'])->on('player_profiles')->restrictOnDelete();
            $table->index(['club_id', 'status', 'incident_date']);
        });
        Schema::create('discipline_case_history', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('club_id');
            $table->unsignedBigInteger('case_id');
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->string('action', 20);
            $table->text('reason')->nullable();
            $table->json('snapshot');
            $table->timestamp('created_at');
            $table->foreign(['club_id', 'case_id'])->references(['club_id', 'id'])->on('discipline_cases')->restrictOnDelete();
            $table->index(['club_id', 'case_id', 'id']);
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('discipline_case_history');
        Schema::dropIfExists('discipline_cases');
    }
};
