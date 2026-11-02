<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bioimpedance_clinic_settings', function (Blueprint $table) {
            $table->boolean('assessment_pdf_enabled')->default(true)->after('reports_module_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('bioimpedance_clinic_settings', function (Blueprint $table) {
            $table->dropColumn('assessment_pdf_enabled');
        });
    }
};
