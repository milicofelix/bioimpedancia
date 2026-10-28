<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const LEGACY_NAME = 'Ricosty'.' Emagrecimento e Estética';

    private const BRAND_NAME = 'BioFlow Análise Corporal';

    private const LEGACY_FOOTER = self::LEGACY_NAME.' - Avaliação corporal e acompanhamento estético';

    private const BRAND_FOOTER = 'BioFlow Análise Corporal - Avaliação corporal e acompanhamento estético';

    public function up(): void
    {
        DB::table('bioimpedance_clinic_settings')
            ->where('display_name', self::LEGACY_NAME)
            ->update(['display_name' => self::BRAND_NAME]);

        DB::table('bioimpedance_clinic_settings')
            ->where('legal_name', self::LEGACY_NAME)
            ->update(['legal_name' => self::BRAND_NAME]);

        DB::table('bioimpedance_clinic_settings')
            ->where('footer_text', self::LEGACY_FOOTER)
            ->update(['footer_text' => self::BRAND_FOOTER]);
    }

    public function down(): void
    {
        DB::table('bioimpedance_clinic_settings')
            ->where('display_name', self::BRAND_NAME)
            ->update(['display_name' => self::LEGACY_NAME]);

        DB::table('bioimpedance_clinic_settings')
            ->where('legal_name', self::BRAND_NAME)
            ->update(['legal_name' => self::LEGACY_NAME]);

        DB::table('bioimpedance_clinic_settings')
            ->where('footer_text', self::BRAND_FOOTER)
            ->update(['footer_text' => self::LEGACY_FOOTER]);
    }
};
