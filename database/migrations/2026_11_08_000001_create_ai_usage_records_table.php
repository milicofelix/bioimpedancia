<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_usage_records', function (Blueprint $table) {
            $table->id();
            $table->string('operation', 40);
            $table->string('model', 120);
            $table->string('service_tier', 30)->nullable();
            $table->string('outcome', 30);
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->unsignedBigInteger('duration_ms');
            $table->unsignedBigInteger('input_tokens')->nullable();
            $table->unsignedBigInteger('output_tokens')->nullable();
            $table->unsignedBigInteger('cached_tokens')->nullable();
            $table->unsignedBigInteger('reasoning_tokens')->nullable();
            $table->unsignedBigInteger('cache_write_30m_tokens')->nullable();
            $table->unsignedBigInteger('cache_write_12h_tokens')->nullable();
            $table->string('cost_status', 40);
            $table->decimal('estimated_cost_usd', 20, 12)->nullable();
            $table->json('price_snapshot')->nullable();
            $table->timestamp('created_at')->index();
            $table->index(['operation', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_usage_records');
    }
};
