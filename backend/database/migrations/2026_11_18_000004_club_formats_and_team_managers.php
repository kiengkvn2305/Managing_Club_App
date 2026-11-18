<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::table('clubs', fn (Blueprint $table) => $table->json('formats')->nullable());
        Schema::create('team_managers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('team_id');
            $table->unsignedBigInteger('club_id');
            $table->foreignId('membership_id')->constrained('club_members')->restrictOnDelete();
            $table->foreign(['club_id', 'team_id'])->references(['club_id', 'id'])->on('teams')->restrictOnDelete();
            $table->unique(['team_id', 'membership_id']);
        });
    }
    public function down(): void {
        Schema::dropIfExists('team_managers');
        Schema::table('clubs', fn (Blueprint $table) => $table->dropColumn('formats'));
    }
};
