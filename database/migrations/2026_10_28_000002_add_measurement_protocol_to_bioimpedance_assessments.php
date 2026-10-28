<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bioimpedance_assessments', function (Blueprint $table) {
            $table->json('measurement_protocol')->nullable()->after('device_manual_version');
        });
    }

    public function down(): void
    {
        Schema::table('bioimpedance_assessments', function (Blueprint $table) {
            $table->dropColumn('measurement_protocol');
        });
    }
};
