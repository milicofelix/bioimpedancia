<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const PREVIOUS_NAME = 'BioFlow Análise Corporal';

    private const BRAND_NAME = 'Vita Scan Análise clínica Biológica';

    private const PREVIOUS_FOOTER = self::PREVIOUS_NAME.' - Avaliação corporal e acompanhamento estético';

    private const BRAND_FOOTER = self::BRAND_NAME.' - Avaliação corporal e acompanhamento estético';

    public function up(): void
    {
        DB::table('bioimpedance_clinic_settings')
            ->where('display_name', self::PREVIOUS_NAME)
            ->update(['display_name' => self::BRAND_NAME]);

        DB::table('bioimpedance_clinic_settings')
            ->where('legal_name', self::PREVIOUS_NAME)
            ->update(['legal_name' => self::BRAND_NAME]);

        DB::table('bioimpedance_clinic_settings')
            ->where('footer_text', self::PREVIOUS_FOOTER)
            ->update(['footer_text' => self::BRAND_FOOTER]);
    }

    public function down(): void
    {
        DB::table('bioimpedance_clinic_settings')
            ->where('display_name', self::BRAND_NAME)
            ->update(['display_name' => self::PREVIOUS_NAME]);

        DB::table('bioimpedance_clinic_settings')
            ->where('legal_name', self::BRAND_NAME)
            ->update(['legal_name' => self::PREVIOUS_NAME]);

        DB::table('bioimpedance_clinic_settings')
            ->where('footer_text', self::BRAND_FOOTER)
            ->update(['footer_text' => self::PREVIOUS_FOOTER]);
    }
};
