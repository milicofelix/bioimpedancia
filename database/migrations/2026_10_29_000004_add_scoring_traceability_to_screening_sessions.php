<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('screening_sessions', function (Blueprint $table) {
            $table->string('scoring_rules_version', 80)->nullable()->after('result_message');
            $table->json('scoring_rules_snapshot')->nullable()->after('scoring_rules_version');
            $table->json('attention_question_codes')->nullable()->after('scoring_rules_snapshot');
            $table->text('attention_notice_snapshot')->nullable()->after('attention_question_codes');
        });
    }

    public function down(): void
    {
        Schema::table('screening_sessions', function (Blueprint $table) {
            $table->dropColumn([
                'scoring_rules_version',
                'scoring_rules_snapshot',
                'attention_question_codes',
                'attention_notice_snapshot',
            ]);
        });
    }
};
