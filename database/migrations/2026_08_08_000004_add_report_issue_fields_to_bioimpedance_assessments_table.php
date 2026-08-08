<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bioimpedance_assessments', function (Blueprint $table) {
            $table->timestamp('report_issued_at')->nullable()->after('cancellation_reason');
            $table->unsignedSmallInteger('report_issue_count')->default(0)->after('report_issued_at');
        });
    }

    public function down(): void
    {
        Schema::table('bioimpedance_assessments', function (Blueprint $table) {
            $table->dropColumn([
                'report_issued_at',
                'report_issue_count',
            ]);
        });
    }
};
