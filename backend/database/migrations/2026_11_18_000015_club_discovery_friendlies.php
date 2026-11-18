<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('clubs', fn (Blueprint $table) => $table->boolean('is_discoverable')->default(false));
        Schema::create('friendly_invitations', function (Blueprint $table) {
            $table->id(); $table->foreignId('source_club_id')->constrained('clubs')->restrictOnDelete(); $table->foreignId('target_club_id')->constrained('clubs')->restrictOnDelete();
            $table->string('pair_key', 64); $table->string('pending_key', 10)->nullable(); $table->string('status', 16);
            $table->date('match_date'); $table->string('match_time', 5)->nullable(); $table->string('location', 160)->nullable(); $table->text('message')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete(); $table->foreignId('responded_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps(); $table->unique(['pair_key', 'pending_key']); $table->index(['source_club_id', 'status']); $table->index(['target_club_id', 'status']);
        });
    }
    public function down(): void { Schema::dropIfExists('friendly_invitations'); Schema::table('clubs', fn (Blueprint $table) => $table->dropColumn('is_discoverable')); }
};
