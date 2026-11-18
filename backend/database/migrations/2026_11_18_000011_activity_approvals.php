<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        foreach (['club_celebrations', 'team_trainings', 'club_awards'] as $table) Schema::table($table, function (Blueprint $t) {
            $t->string('approval_status', 16)->default('approved'); $t->unsignedBigInteger('submitted_by')->nullable();
        });
        Schema::table('team_trainings', fn (Blueprint $t) => $t->string('training_status', 16)->default('scheduled'));
    }
    public function down(): void {
        Schema::table('team_trainings', fn (Blueprint $t) => $t->dropColumn('training_status'));
        foreach (['club_celebrations', 'team_trainings', 'club_awards'] as $table) Schema::table($table, fn (Blueprint $t) => $t->dropColumn(['approval_status', 'submitted_by']));
    }
};
