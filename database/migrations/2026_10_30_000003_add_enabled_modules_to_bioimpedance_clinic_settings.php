<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bioimpedance_clinic_settings', function (Blueprint $table) {
            $table->boolean('assessment_module_enabled')->default(true)->after('scale_model');
            $table->boolean('screening_module_enabled')->default(true)->after('assessment_module_enabled');
            $table->boolean('reports_module_enabled')->default(true)->after('screening_module_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('bioimpedance_clinic_settings', function (Blueprint $table) {
            $table->dropColumn([
                'assessment_module_enabled',
                'screening_module_enabled',
                'reports_module_enabled',
            ]);
        });
    }
};
