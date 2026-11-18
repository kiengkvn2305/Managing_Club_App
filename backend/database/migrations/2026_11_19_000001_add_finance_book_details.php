<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('finance_transactions', function (Blueprint $table) {
            $table->string('category', 120)->nullable();
            $table->string('paid_by', 120)->nullable();
            $table->text('notes')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('finance_transactions', fn (Blueprint $table) => $table->dropColumn(['category', 'paid_by', 'notes']));
    }
};
