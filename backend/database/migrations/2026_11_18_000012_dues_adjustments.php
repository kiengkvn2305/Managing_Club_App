<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('team_dues_adjustments', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('club_id'); $table->unsignedBigInteger('team_id'); $table->unsignedBigInteger('player_id');
            $table->date('month'); $table->string('old_status', 10); $table->string('new_status', 10); $table->unsignedBigInteger('amount');
            $table->unsignedBigInteger('old_transaction_id')->nullable(); $table->unsignedBigInteger('new_transaction_id')->nullable();
            $table->string('reason', 255); $table->unsignedBigInteger('created_by'); $table->timestamps();
            $table->index(['club_id', 'team_id', 'player_id', 'month']);
        });
    }
    public function down(): void { Schema::dropIfExists('team_dues_adjustments'); }
};
