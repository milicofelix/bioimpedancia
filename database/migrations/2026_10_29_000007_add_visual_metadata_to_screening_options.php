<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('screening_options', function (Blueprint $table) {
            $table->json('display_metadata')->nullable()->after('position');
        });
    }

    public function down(): void
    {
        Schema::table('screening_options', function (Blueprint $table) {
            $table->dropColumn('display_metadata');
        });
    }
};
