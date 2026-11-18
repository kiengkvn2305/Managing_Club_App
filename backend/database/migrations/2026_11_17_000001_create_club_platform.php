<?php

use App\Enums\ClubRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_system_admin')->default(false);
            $table->boolean('is_active')->default(true);
        });
        Schema::create('clubs', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('slug', 160)->unique();
            $table->text('description')->nullable();
            $table->string('city', 120)->nullable();
            $table->string('home_ground', 160)->nullable();
            $table->enum('format', ['5', '7', '11'])->default('7');
            $table->enum('status', ['active', 'archived'])->default('active');
            $table->timestamps();
        });
        Schema::create('club_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->enum('role', array_column(ClubRole::cases(), 'value'));
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();
            $table->unique(['club_id', 'user_id']);
            $table->index(['user_id', 'status']);
        });
        Schema::create('teams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained()->restrictOnDelete();
            $table->string('name', 120);
            $table->enum('format', ['5', '7', '11']);
            $table->timestamps();
            $table->unique(['club_id', 'name']);
            $table->unique(['club_id', 'id']);
        });
        Schema::create('player_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('team_id')->nullable();
            $table->string('full_name', 120);
            $table->string('email')->nullable();
            $table->string('phone', 25)->nullable();
            $table->date('date_of_birth')->nullable();
            $table->unsignedSmallInteger('jersey_number')->nullable();
            $table->unsignedSmallInteger('current_jersey')->nullable();
            $table->string('position', 5)->nullable();
            $table->enum('preferred_foot', ['left', 'right', 'both'])->nullable();
            $table->enum('status', ['active', 'inactive', 'injured'])->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->foreign(['club_id', 'team_id'])->references(['club_id', 'id'])->on('teams')->restrictOnDelete();
            $table->unique(['club_id', 'email']);
            $table->unique(['club_id', 'team_id', 'current_jersey']);
            $table->index(['club_id', 'status', 'full_name']);
        });
        Schema::create('club_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained()->restrictOnDelete();
            $table->foreignId('invited_by')->constrained('users')->restrictOnDelete();
            $table->string('email');
            $table->string('role', 30);
            $table->string('token_hash', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();
            $table->unique(['club_id', 'email']);
        });
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained()->restrictOnDelete();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->string('action', 60);
            $table->string('subject_type', 60);
            $table->unsignedBigInteger('subject_id');
            $table->timestamps();
            $table->index(['club_id', 'created_at']);
        });
    }

    public function down(): void
    {
        foreach (['audit_logs', 'club_invitations', 'player_profiles', 'teams', 'club_members', 'clubs'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['is_system_admin', 'is_active']));
    }
};
