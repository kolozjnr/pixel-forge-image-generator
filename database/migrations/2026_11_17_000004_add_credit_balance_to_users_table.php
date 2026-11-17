<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('credit_balance_nano_usd')->default(0);
            $table->unsignedBigInteger('reserved_credit_nano_usd')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['credit_balance_nano_usd', 'reserved_credit_nano_usd']);
        });
    }
};
