<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('recruitment_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained()->restrictOnDelete();
            $table->string('title');
            $table->string('position', 100)->nullable();
            $table->text('requirements')->nullable();
            $table->date('deadline')->nullable();
            $table->string('status', 20)->default('draft');
            $table->timestamps();
            $table->unique(['club_id', 'id']);
            $table->index(['club_id', 'status']);
        });
        Schema::create('recruitment_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('post_id');
            $table->string('full_name');
            $table->date('date_of_birth')->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 30)->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 20)->default('new');
            $table->timestamps();
            $table->foreign(['club_id', 'post_id'])->references(['club_id', 'id'])->on('recruitment_posts')->restrictOnDelete();
            $table->index(['club_id', 'post_id', 'status']);
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('recruitment_applications');
        Schema::dropIfExists('recruitment_posts');
    }
};
