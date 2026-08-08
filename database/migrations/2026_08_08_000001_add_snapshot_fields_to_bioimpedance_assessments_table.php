<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bioimpedance_assessments', function (Blueprint $table) {
            $table->unsignedTinyInteger('age_at_assessment')->nullable()->after('user_id');
            $table->decimal('height_cm_at_assessment', 4, 1)->nullable()->after('age_at_assessment');
            $table->string('biological_sex_at_assessment', 10)->nullable()->after('height_cm_at_assessment');
            $table->string('device_model', 40)->default('HBF-514C')->after('biological_sex_at_assessment');
            $table->string('reference_version', 40)->default('1.0.0')->after('device_model');
        });
    }

    public function down(): void
    {
        Schema::table('bioimpedance_assessments', function (Blueprint $table) {
            $table->dropColumn([
                'age_at_assessment',
                'height_cm_at_assessment',
                'biological_sex_at_assessment',
                'device_model',
                'reference_version',
            ]);
        });
    }
};
