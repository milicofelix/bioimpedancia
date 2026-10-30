<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bioimpedance_extended_metrics', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('bioimpedance_assessment_id');
            $table->string('metric_code', 80);
            $table->string('original_label', 160);
            $table->decimal('numeric_value', 12, 4)->nullable();
            $table->text('text_value')->nullable();
            $table->string('unit', 30)->nullable();
            $table->string('application_classification', 120)->nullable();
            $table->decimal('printed_minimum', 12, 4)->nullable();
            $table->decimal('printed_maximum', 12, 4)->nullable();
            $table->decimal('weight_proportion_percentage', 8, 3)->nullable();
            $table->string('source_application', 30);
            $table->string('source_layout', 60);
            $table->text('raw_extracted_value');
            $table->string('reference_version', 60);
            $table->timestamps();

            $table->foreign('bioimpedance_assessment_id', 'bio_extended_assessment_fk')
                ->references('id')
                ->on('bioimpedance_assessments')
                ->cascadeOnDelete();

            $table->unique(
                ['bioimpedance_assessment_id', 'metric_code'],
                'bio_extended_assessment_metric_unique',
            );
            $table->index('metric_code', 'bio_extended_metric_code_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bioimpedance_extended_metrics');
    }
};
