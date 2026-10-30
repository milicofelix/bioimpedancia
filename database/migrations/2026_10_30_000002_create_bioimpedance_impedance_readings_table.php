<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bioimpedance_impedance_readings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('bioimpedance_assessment_id');
            $table->unsignedSmallInteger('frequency_khz');
            $table->string('region', 20);
            $table->string('side', 10);
            $table->decimal('impedance_ohms', 9, 2)->nullable();
            $table->string('raw_value', 255)->nullable();
            $table->string('reading_state', 20);
            $table->string('device_version', 80);
            $table->string('source_application', 30);
            $table->string('source_layout', 60);
            $table->timestamps();

            $table->foreign('bioimpedance_assessment_id', 'bio_impedance_reading_assessment_fk')
                ->references('id')
                ->on('bioimpedance_assessments')
                ->cascadeOnDelete();
            $table->unique(
                ['bioimpedance_assessment_id', 'frequency_khz', 'region', 'side'],
                'bio_impedance_reading_unique',
            );
            $table->index(['frequency_khz', 'region', 'side'], 'bio_impedance_dimension_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bioimpedance_impedance_readings');
    }
};
