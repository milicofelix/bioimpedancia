<?php

use App\Models\Bioimpedance\BioimpedanceClinicSetting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bioimpedance_clinic_settings', function (Blueprint $table) {
            $table->string('scale_model', 40)
                ->default(BioimpedanceClinicSetting::SCALE_MODEL_OMRON_HBF_514C)
                ->after('technical_notice');
        });
    }

    public function down(): void
    {
        Schema::table('bioimpedance_clinic_settings', function (Blueprint $table) {
            $table->dropColumn('scale_model');
        });
    }
};
