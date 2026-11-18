<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('sponsors', function (Blueprint $table) {
            $table->id(); $table->foreignId('club_id')->constrained()->restrictOnDelete();
            $table->string('name'); $table->string('contact_name')->nullable(); $table->string('email')->nullable(); $table->string('phone', 30)->nullable(); $table->text('notes')->nullable(); $table->timestamps();
            $table->unique(['club_id', 'id']); $table->index(['club_id', 'name']);
        });
        Schema::create('sponsorships', function (Blueprint $table) {
            $table->id(); $table->foreignId('club_id')->constrained()->restrictOnDelete(); $table->unsignedBigInteger('sponsor_id');
            $table->string('title'); $table->string('type', 20); $table->decimal('value', 15, 2); $table->date('starts_on'); $table->date('ends_on');
            $table->text('benefits')->nullable(); $table->text('notes')->nullable(); $table->timestamps();
            $table->unique(['club_id', 'id']); $table->foreign(['club_id', 'sponsor_id'])->references(['club_id', 'id'])->on('sponsors')->restrictOnDelete(); $table->index(['club_id', 'ends_on']);
        });
        Schema::create('sponsorship_receipts', function (Blueprint $table) {
            $table->id(); $table->foreignId('club_id')->constrained()->restrictOnDelete(); $table->unsignedBigInteger('sponsorship_id');
            $table->decimal('value', 15, 2); $table->date('received_on'); $table->text('notes')->nullable(); $table->foreignId('actor_id')->constrained('users')->restrictOnDelete(); $table->timestamps();
            $table->foreign(['club_id', 'sponsorship_id'])->references(['club_id', 'id'])->on('sponsorships')->restrictOnDelete(); $table->index(['club_id', 'sponsorship_id']);
        });
    }
    public function down(): void { Schema::dropIfExists('sponsorship_receipts'); Schema::dropIfExists('sponsorships'); Schema::dropIfExists('sponsors'); }
};
