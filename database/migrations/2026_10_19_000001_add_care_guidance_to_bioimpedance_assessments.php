<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const APPROVER_FOREIGN_KEY = 'bio_assess_care_approver_fk';

    private const PURPOSE_INDEX = 'bio_ai_requests_purpose_idx';

    public function up(): void
    {
        if (! Schema::hasColumn('bioimpedance_assessments', 'care_guidance')) {
            Schema::table('bioimpedance_assessments', function (Blueprint $table) {
                $table->text('care_guidance')->nullable()->after('notes');
            });
        }

        if (! Schema::hasColumn('bioimpedance_assessments', 'care_guidance_sources')) {
            Schema::table('bioimpedance_assessments', function (Blueprint $table) {
                $table->json('care_guidance_sources')->nullable()->after('care_guidance');
            });
        }

        if (! Schema::hasColumn('bioimpedance_assessments', 'care_guidance_approved_by_user_id')) {
            Schema::table('bioimpedance_assessments', function (Blueprint $table) {
                $table->unsignedBigInteger('care_guidance_approved_by_user_id')->nullable()->after('care_guidance_sources');
            });
        }

        if ($this->approverForeignKeyName() === null) {
            Schema::table('bioimpedance_assessments', function (Blueprint $table) {
                $table->foreign('care_guidance_approved_by_user_id', self::APPROVER_FOREIGN_KEY)
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('bioimpedance_assessments', 'care_guidance_approved_at')) {
            Schema::table('bioimpedance_assessments', function (Blueprint $table) {
                $table->timestamp('care_guidance_approved_at')->nullable()->after('care_guidance_approved_by_user_id');
            });
        }

        if (! Schema::hasColumn('bioimpedance_ai_analysis_requests', 'purpose')) {
            Schema::table('bioimpedance_ai_analysis_requests', function (Blueprint $table) {
                $table->string('purpose', 40)->default('observation')->after('status');
            });
        }

        if (! Schema::hasIndex('bioimpedance_ai_analysis_requests', self::PURPOSE_INDEX)) {
            Schema::table('bioimpedance_ai_analysis_requests', function (Blueprint $table) {
                $table->index('purpose', self::PURPOSE_INDEX);
            });
        }

        if (! Schema::hasColumn('bioimpedance_ai_analysis_outputs', 'care_guidance')) {
            Schema::table('bioimpedance_ai_analysis_outputs', function (Blueprint $table) {
                $table->text('care_guidance')->nullable()->after('professional_observation');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('bioimpedance_ai_analysis_outputs', 'care_guidance')) {
            Schema::table('bioimpedance_ai_analysis_outputs', function (Blueprint $table) {
                $table->dropColumn('care_guidance');
            });
        }

        if (Schema::hasIndex('bioimpedance_ai_analysis_requests', self::PURPOSE_INDEX)) {
            Schema::table('bioimpedance_ai_analysis_requests', function (Blueprint $table) {
                $table->dropIndex(self::PURPOSE_INDEX);
            });
        }

        if (Schema::hasColumn('bioimpedance_ai_analysis_requests', 'purpose')) {
            Schema::table('bioimpedance_ai_analysis_requests', function (Blueprint $table) {
                $table->dropColumn('purpose');
            });
        }

        $approverForeignKey = $this->approverForeignKeyName();
        if ($approverForeignKey !== null) {
            Schema::table('bioimpedance_assessments', function (Blueprint $table) use ($approverForeignKey) {
                $table->dropForeign($approverForeignKey);
            });
        }

        foreach (['care_guidance_approved_at', 'care_guidance_approved_by_user_id', 'care_guidance_sources', 'care_guidance'] as $column) {
            if (Schema::hasColumn('bioimpedance_assessments', $column)) {
                Schema::table('bioimpedance_assessments', function (Blueprint $table) use ($column) {
                    $table->dropColumn($column);
                });
            }
        }
    }

    private function approverForeignKeyName(): ?string
    {
        $foreignKey = collect(Schema::getForeignKeys('bioimpedance_assessments'))
            ->first(fn (array $foreignKey) => $foreignKey['columns'] === ['care_guidance_approved_by_user_id']);

        return $foreignKey['name'] ?? null;
    }
};
