<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bioimpedance_assessments', function (Blueprint $table) {
            $table->boolean('athlete_mode_at_assessment')->nullable()->after('reference_version');
            $table->string('measurement_application', 20)->nullable()->after('athlete_mode_at_assessment');
            $table->string('device_model_code', 30)->nullable()->after('measurement_application');
            $table->string('device_manual_version', 20)->nullable()->after('device_model_code');
        });
    }

    public function down(): void
    {
        Schema::table('bioimpedance_assessments', function (Blueprint $table) {
            $table->dropColumn([
                'athlete_mode_at_assessment',
                'measurement_application',
                'device_model_code',
                'device_manual_version',
            ]);
        });
    }
};
