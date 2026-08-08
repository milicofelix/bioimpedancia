<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bioimpedance_assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bioimpedance_client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->dateTime('evaluated_at');
            $table->decimal('weight_kg', 5, 2);
            $table->decimal('scale_bmi', 4, 1)->nullable();
            $table->decimal('calculated_bmi', 4, 1);
            $table->decimal('bmi_difference', 4, 2)->nullable();
            $table->decimal('body_fat_percentage', 4, 1)->nullable();
            $table->decimal('skeletal_muscle_percentage', 4, 1)->nullable();
            $table->unsignedSmallInteger('resting_metabolism_kcal')->nullable();
            $table->unsignedSmallInteger('body_age')->nullable();
            $table->decimal('visceral_fat_level', 4, 1)->nullable();
            $table->json('analysis')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['bioimpedance_client_id', 'evaluated_at'], 'bio_assessments_client_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bioimpedance_assessments');
    }
};
