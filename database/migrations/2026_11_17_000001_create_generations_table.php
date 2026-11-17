<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('generations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('provider_id')->nullable()->unique();
            $table->uuid('idempotency_key')->unique();
            $table->string('status')->default('queued')->index();
            $table->text('prompt');
            $table->string('size', 20);
            $table->string('quality', 20);
            $table->json('reference_images');
            $table->json('metadata');
            $table->string('image_path')->nullable();
            $table->text('provider_image_url')->nullable();
            $table->timestamp('image_url_expires_at')->nullable();
            $table->unsignedInteger('input_text_tokens')->nullable();
            $table->unsignedInteger('input_image_tokens')->nullable();
            $table->unsignedInteger('output_tokens')->nullable();
            $table->unsignedInteger('reasoning_tokens')->nullable();
            $table->unsignedInteger('total_tokens')->nullable();
            $table->unsignedBigInteger('provider_cost_nano_usd')->nullable();
            $table->unsignedBigInteger('customer_cost_nano_usd')->nullable();
            $table->json('rates')->nullable();
            $table->json('provider_error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('generations');
    }
};
