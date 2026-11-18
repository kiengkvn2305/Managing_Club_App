<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('match_lineups', function (Blueprint $table) {
            $table->unsignedBigInteger('club_match_id')->nullable();
            $table->foreign(['club_id', 'club_match_id'])->references(['club_id', 'id'])->on('club_matches')->restrictOnDelete();
            $table->unique(['club_id', 'club_match_id']);
        });
    }
    public function down(): void {
        Schema::table('match_lineups', function (Blueprint $table) {
            $table->dropForeign(['club_id', 'club_match_id']); $table->dropUnique(['club_id', 'club_match_id']); $table->dropColumn('club_match_id');
        });
    }
};
