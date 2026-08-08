<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bioimpedance_assessments', function (Blueprint $table) {
            if (! Schema::hasColumn('bioimpedance_assessments', 'corrected_by_user_id')) {
                $table->foreignId('corrected_by_user_id')->nullable()->after('user_id')->constrained('users')->nullOnDelete();
            }

            if (! Schema::hasColumn('bioimpedance_assessments', 'correction_count')) {
                $table->unsignedSmallInteger('correction_count')->default(0)->after('corrected_by_user_id');
            }

            if (! Schema::hasColumn('bioimpedance_assessments', 'canceled_at')) {
                $table->timestamp('canceled_at')->nullable()->after('notes');
            }

            if (! Schema::hasColumn('bioimpedance_assessments', 'canceled_by_user_id')) {
                $table->foreignId('canceled_by_user_id')->nullable()->after('canceled_at')->constrained('users')->nullOnDelete();
            }

            if (! Schema::hasColumn('bioimpedance_assessments', 'cancellation_reason')) {
                $table->text('cancellation_reason')->nullable()->after('canceled_by_user_id');
            }
        });

        if (! Schema::hasTable('bioimpedance_assessment_audits')) {
            Schema::create('bioimpedance_assessment_audits', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('bioimpedance_assessment_id');
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->string('action', 40);
                $table->text('reason')->nullable();
                $table->json('old_values')->nullable();
                $table->json('new_values')->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->timestamps();

                $table->foreign('bioimpedance_assessment_id', 'bio_assessment_audit_assessment_fk')
                    ->references('id')
                    ->on('bioimpedance_assessments')
                    ->cascadeOnDelete();
                $table->index(['bioimpedance_assessment_id', 'action'], 'bio_assessment_audit_action_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('bioimpedance_assessment_audits');

        Schema::table('bioimpedance_assessments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('corrected_by_user_id');
            $table->dropConstrainedForeignId('canceled_by_user_id');
            $table->dropColumn([
                'correction_count',
                'canceled_at',
                'cancellation_reason',
            ]);
        });
    }
};
