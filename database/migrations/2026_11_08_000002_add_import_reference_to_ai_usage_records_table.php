<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_usage_records', function (Blueprint $table) {
            $table->string('import_reference', 96)->nullable()->unique();
        });
    }

    public function down(): void
    {
        Schema::table('ai_usage_records', function (Blueprint $table) {
            $table->dropUnique(['import_reference']);
            $table->dropColumn('import_reference');
        });
    }
};
