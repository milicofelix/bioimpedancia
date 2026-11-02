<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bioimpedance_report_shares', function (Blueprint $table) {
            $table->text('encrypted_token')->nullable()->after('token_hash');
        });
    }

    public function down(): void
    {
        Schema::table('bioimpedance_report_shares', function (Blueprint $table) {
            $table->dropColumn('encrypted_token');
        });
    }
};
