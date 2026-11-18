<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('clubs', function (Blueprint $table) { $table->string('avatar_path')->nullable(); $table->string('background_path')->nullable(); });
        Schema::table('player_profiles', fn (Blueprint $table) => $table->string('avatar_path')->nullable());
        Schema::table('users', fn (Blueprint $table) => $table->string('avatar_path')->nullable());
    }
    public function down(): void {
        Schema::table('clubs', fn (Blueprint $table) => $table->dropColumn(['avatar_path', 'background_path']));
        Schema::table('player_profiles', fn (Blueprint $table) => $table->dropColumn('avatar_path'));
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('avatar_path'));
    }
};
