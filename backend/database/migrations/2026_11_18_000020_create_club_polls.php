<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('club_polls', function (Blueprint $table) {
            $table->id(); $table->foreignId('club_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->string('title', 200); $table->text('description')->nullable();
            $table->timestamp('deadline'); $table->timestamps();
            $table->unique(['club_id', 'id']);
        });
        Schema::create('club_poll_options', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('club_id'); $table->unsignedBigInteger('poll_id');
            $table->string('label', 200); $table->unsignedInteger('sort_order'); $table->timestamps();
            $table->foreign(['club_id', 'poll_id'])->references(['club_id', 'id'])->on('club_polls')->restrictOnDelete();
            $table->unique(['club_id', 'poll_id', 'id']);
        });
        Schema::create('club_poll_electors', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('club_id'); $table->unsignedBigInteger('poll_id');
            $table->foreignId('user_id')->constrained()->restrictOnDelete(); $table->timestamps();
            $table->foreign(['club_id', 'poll_id'])->references(['club_id', 'id'])->on('club_polls')->restrictOnDelete();
            $table->unique(['club_id', 'poll_id', 'user_id']);
        });
        Schema::create('club_poll_votes', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('club_id'); $table->unsignedBigInteger('poll_id');
            $table->unsignedBigInteger('user_id'); $table->unsignedBigInteger('option_id'); $table->timestamps();
            $table->foreign(['club_id', 'poll_id', 'user_id'])->references(['club_id', 'poll_id', 'user_id'])->on('club_poll_electors')->restrictOnDelete();
            $table->foreign(['club_id', 'poll_id', 'option_id'])->references(['club_id', 'poll_id', 'id'])->on('club_poll_options')->restrictOnDelete();
            $table->unique(['poll_id', 'user_id']);
        });
    }
    public function down(): void
    {
        foreach (['club_poll_votes', 'club_poll_electors', 'club_poll_options', 'club_polls'] as $table) Schema::dropIfExists($table);
    }
};
