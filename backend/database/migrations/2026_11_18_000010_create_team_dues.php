<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('team_dues_rates', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('club_id'); $table->unsignedBigInteger('team_id');
            $table->date('effective_month'); $table->unsignedBigInteger('amount'); $table->timestamps();
            $table->unique(['team_id', 'effective_month']);
        });
        Schema::create('team_membership_periods', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('club_id'); $table->unsignedBigInteger('team_id'); $table->unsignedBigInteger('player_id');
            $table->date('joined_on'); $table->date('left_on')->nullable(); $table->timestamps();
            $table->index(['club_id', 'team_id', 'player_id']);
        });
        Schema::create('team_dues_entries', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('club_id'); $table->unsignedBigInteger('team_id'); $table->unsignedBigInteger('player_id');
            $table->date('month'); $table->string('status', 10); $table->unsignedBigInteger('amount');
            $table->unsignedBigInteger('finance_transaction_id')->nullable(); $table->unsignedBigInteger('created_by'); $table->timestamps();
            $table->unique(['team_id', 'player_id', 'month']);
        });
        // Older assignments have no join date. Begin tracking from installation, never invent past debt.
        if (! Schema::getConnection()->pretending()) {
            $today = now('Asia/Ho_Chi_Minh')->toDateString();
            foreach (DB::table('team_players')->get() as $row) DB::table('team_membership_periods')->insert(['club_id' => $row->club_id, 'team_id' => $row->team_id, 'player_id' => $row->player_id, 'joined_on' => $today, 'created_at' => now(), 'updated_at' => now()]);
        }
    }
    public function down(): void
    {
        Schema::dropIfExists('team_dues_entries'); Schema::dropIfExists('team_membership_periods'); Schema::dropIfExists('team_dues_rates');
    }
};
