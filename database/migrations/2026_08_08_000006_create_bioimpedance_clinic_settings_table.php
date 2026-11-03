<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
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

        // Keep this insert tied to the schema created above. Application defaults
        // may contain columns introduced by migrations that have not run yet.
        $createdAt = now();

        DB::table('bioimpedance_clinic_settings')->insert([
            'display_name' => 'Vita Scan Análise clínica Biológica',
            'legal_name' => 'Vita Scan Análise clínica Biológica',
            'document' => null,
            'phone' => null,
            'whatsapp' => null,
            'email' => null,
            'address' => null,
            'instagram' => null,
            'website' => null,
            'primary_color' => '#d88b9a',
            'secondary_color' => '#4a4a4a',
            'logo_url' => '/images/brand/ricosty-logo.png',
            'contact' => 'Avaliação corporal e acompanhamento estético',
            'footer_text' => 'Vita Scan Análise clínica Biológica - Avaliação corporal e acompanhamento estético',
            'technical_notice' => 'Os resultados de bioimpedância são estimativas e podem variar conforme hidratação, alimentação, ciclo hormonal, medicamentos e condições de medição. Este documento não substitui avaliação médica ou nutricional.',
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('bioimpedance_clinic_settings');
    }
};
