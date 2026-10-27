<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bioimpedance_segmental_measurements', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('bioimpedance_assessment_id');
            $table->string('type', 20);
            $table->string('region', 20);
            $table->string('side', 10);
            $table->decimal('mass_kg', 6, 2)->nullable();
            $table->decimal('reference_percentage', 7, 2)->nullable();
            $table->string('classification', 30)->nullable();
            $table->decimal('reference_min_percentage', 6, 2)->nullable();
            $table->decimal('reference_max_percentage', 6, 2)->nullable();
            $table->string('reference_version', 40);
            $table->json('raw_value')->nullable();
            $table->timestamps();

            $table->foreign('bioimpedance_assessment_id', 'bio_segment_assessment_fk')
                ->references('id')
                ->on('bioimpedance_assessments')
                ->cascadeOnDelete();
            $table->unique(
                ['bioimpedance_assessment_id', 'type', 'region', 'side'],
                'bio_segment_measurement_unique',
            );
            $table->index(['type', 'region', 'side'], 'bio_segment_dimension_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bioimpedance_segmental_measurements');
    }
};
