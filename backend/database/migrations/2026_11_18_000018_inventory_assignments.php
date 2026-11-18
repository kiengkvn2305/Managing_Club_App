<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('player_profiles', fn (Blueprint $table) => $table->unique(['club_id', 'id']));
        Schema::create('inventory_assignments', function (Blueprint $table) {
            $table->id(); $table->foreignId('club_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('inventory_item_id'); $table->unsignedBigInteger('player_id');
            $table->string('player_name', 120); $table->unsignedInteger('quantity'); $table->unsignedInteger('remaining');
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete(); $table->timestamps();
            $table->unique(['club_id', 'inventory_item_id', 'id']);
            $table->foreign(['club_id', 'inventory_item_id'])->references(['club_id', 'id'])->on('inventory_items')->restrictOnDelete();
            $table->foreign(['club_id', 'player_id'])->references(['club_id', 'id'])->on('player_profiles')->restrictOnDelete();
            $table->index(['club_id', 'player_id']);
        });
        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->unsignedBigInteger('inventory_assignment_id')->nullable();
            $table->foreign(['club_id', 'inventory_item_id', 'inventory_assignment_id'], 'inventory_movement_assignment_fk')->references(['club_id', 'inventory_item_id', 'id'])->on('inventory_assignments')->restrictOnDelete();
        });
    }
    public function down(): void
    {
        Schema::table('inventory_movements', function (Blueprint $table) { $table->dropForeign('inventory_movement_assignment_fk'); $table->dropColumn('inventory_assignment_id'); });
        Schema::dropIfExists('inventory_assignments');
        Schema::table('player_profiles', fn (Blueprint $table) => $table->dropUnique(['club_id', 'id']));
    }
};
