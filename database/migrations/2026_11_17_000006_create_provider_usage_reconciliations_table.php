<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provider_usage_reconciliations', function (Blueprint $table) {
            $table->id();
            $table->date('usage_date')->unique();
            $table->unsignedInteger('generation_count');
            $table->unsignedBigInteger('provider_cost_nano_usd');
            $table->unsignedBigInteger('input_text_tokens');
            $table->unsignedBigInteger('input_image_tokens');
            $table->unsignedBigInteger('output_tokens');
            $table->unsignedBigInteger('reasoning_tokens');
            $table->json('provider_payload');
            $table->timestamp('reconciled_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_usage_reconciliations');
    }
};
