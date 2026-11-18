<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('inventory_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('category', 100)->nullable();
            $table->string('unit', 30)->default('cái');
            $table->string('kind', 20)->default('consumable');
            $table->unsignedInteger('quantity')->default(0);
            $table->unsignedInteger('low_stock_threshold')->default(0);
            $table->text('notes')->nullable();
            $table->boolean('archived')->default(false);
            $table->timestamps();
            $table->unique(['club_id', 'id']);
            $table->index(['club_id', 'archived', 'name']);
        });
        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained()->restrictOnDelete();
            $table->foreignId('inventory_item_id')->constrained('inventory_items')->restrictOnDelete();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->string('type', 20);
            $table->integer('change');
            $table->unsignedInteger('balance_after');
            $table->string('reason', 500);
            $table->timestamps();
            $table->foreign(['club_id', 'inventory_item_id'])->references(['club_id', 'id'])->on('inventory_items')->restrictOnDelete();
            $table->index(['club_id', 'inventory_item_id', 'id']);
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');
        Schema::dropIfExists('inventory_items');
    }
};
