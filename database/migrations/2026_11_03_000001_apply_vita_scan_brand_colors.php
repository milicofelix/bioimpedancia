<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bioimpedance_clinic_settings', function (Blueprint $table) {
            $table->string('primary_color', 7)->default('#087f98')->change();
            $table->string('secondary_color', 7)->default('#052f59')->change();
        });

        DB::transaction(function () {
            // Only replace the previous defaults; preserve custom clinic colors.
            DB::table('bioimpedance_clinic_settings')
                ->whereRaw('LOWER(primary_color) = ?', ['#d88b9a'])
                ->update(['primary_color' => '#087f98']);

            DB::table('bioimpedance_clinic_settings')
                ->whereRaw('LOWER(secondary_color) = ?', ['#4a4a4a'])
                ->update(['secondary_color' => '#052f59']);

            DB::table('bioimpedance_clinic_settings')
                ->where('logo_url', '/images/brand/ricosty-logo.png')
                ->update(['logo_url' => '/images/brand/bioflow-mark.png']);
        });
    }

    public function down(): void
    {
        Schema::table('bioimpedance_clinic_settings', function (Blueprint $table) {
            $table->string('primary_color', 7)->default('#d88b9a')->change();
            $table->string('secondary_color', 7)->default('#4a4a4a')->change();
        });

        // Keep the saved branding on rollback: its previous origin cannot be
        // distinguished from a color or logo deliberately chosen by an admin.
    }
};
