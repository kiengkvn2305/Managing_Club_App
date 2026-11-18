<?php

use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('board_membership_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained()->cascadeOnDelete();
            $table->foreignId('player_id')->constrained('player_profiles')->cascadeOnDelete();
            $table->date('starts_month');
            $table->date('ends_before')->nullable();
            $table->timestamps();
            $table->index(['club_id', 'player_id', 'starts_month']);
        });
        Schema::create('shared_board_dues_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained()->cascadeOnDelete();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->date('effective_month');
            $table->unsignedBigInteger('amount');
            $table->timestamps();
            $table->index(['club_id', 'team_id', 'effective_month']);
        });
        foreach (DB::table('club_board_members')->get(['club_id', 'player_id', 'created_at']) as $member) {
            $start = CarbonImmutable::parse($member->created_at, 'UTC')->setTimezone('Asia/Ho_Chi_Minh')->startOfMonth()->toDateString();
            DB::table('board_membership_periods')->insert(['club_id' => $member->club_id, 'player_id' => $member->player_id, 'starts_month' => $start, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function down(): void { Schema::dropIfExists('shared_board_dues_rates'); Schema::dropIfExists('board_membership_periods'); }
};
