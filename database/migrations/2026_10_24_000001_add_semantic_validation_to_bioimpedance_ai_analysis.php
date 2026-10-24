<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bioimpedance_ai_analysis_requests', function (Blueprint $table) {
            $table->string('provider', 64)->nullable()->after('model_name');
            $table->string('attempted_provider', 64)->nullable()->after('provider');
            $table->boolean('fallback_used')->default(false)->after('attempted_provider');
            $table->text('fallback_reason')->nullable()->after('fallback_used');
        });

        Schema::table('bioimpedance_ai_analysis_outputs', function (Blueprint $table) {
            $table->json('structured_claims')->nullable()->after('raw_response');
            $table->json('blocked_claims')->nullable()->after('structured_claims');
        });
    }

    public function down(): void
    {
        Schema::table('bioimpedance_ai_analysis_outputs', function (Blueprint $table) {
            $table->dropColumn(['structured_claims', 'blocked_claims']);
        });

        Schema::table('bioimpedance_ai_analysis_requests', function (Blueprint $table) {
            $table->dropColumn(['provider', 'attempted_provider', 'fallback_used', 'fallback_reason']);
        });
    }
};
