<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('planned_expenses', function (Blueprint $table) {
            $table->id(); $table->foreignId('club_id')->constrained()->restrictOnDelete();
            $table->string('description', 255); $table->unsignedBigInteger('amount')->nullable(); $table->text('notes')->nullable();
            $table->date('effective_from'); $table->date('effective_to')->nullable(); $table->boolean('cancelled')->default(false);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete(); $table->timestamps();
            $table->index(['club_id', 'effective_from', 'effective_to']);
        });
    }
    public function down(): void { Schema::dropIfExists('planned_expenses'); }
};
