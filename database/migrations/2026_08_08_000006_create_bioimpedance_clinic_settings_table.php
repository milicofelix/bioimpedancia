<?php

use App\Models\Bioimpedance\BioimpedanceClinicSetting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bioimpedance_clinic_settings', function (Blueprint $table) {
            $table->id();
            $table->string('display_name');
            $table->string('legal_name')->nullable();
            $table->string('document', 32)->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('whatsapp', 40)->nullable();
            $table->string('email')->nullable();
            $table->string('address')->nullable();
            $table->string('instagram')->nullable();
            $table->string('website')->nullable();
            $table->string('primary_color', 7)->default('#d88b9a');
            $table->string('secondary_color', 7)->default('#4a4a4a');
            $table->string('logo_url')->nullable();
            $table->string('contact')->nullable();
            $table->text('footer_text')->nullable();
            $table->text('technical_notice')->nullable();
            $table->timestamps();
        });

        BioimpedanceClinicSetting::query()->create(BioimpedanceClinicSetting::DEFAULTS);
    }

    public function down(): void
    {
        Schema::dropIfExists('bioimpedance_clinic_settings');
    }
};
