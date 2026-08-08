<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bioimpedance_clients', function (Blueprint $table) {
            $table->timestamp('privacy_exported_at')->nullable()->after('inactivated_at');
            $table->unsignedInteger('privacy_export_count')->default(0)->after('privacy_exported_at');
            $table->timestamp('anonymized_at')->nullable()->after('privacy_export_count');
            $table->foreignId('anonymized_by_user_id')->nullable()->after('anonymized_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('bioimpedance_clients', function (Blueprint $table) {
            $table->dropConstrainedForeignId('anonymized_by_user_id');
            $table->dropColumn([
                'privacy_exported_at',
                'privacy_export_count',
                'anonymized_at',
            ]);
        });
    }
};
