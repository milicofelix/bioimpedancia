<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('screening_sessions', function (Blueprint $table) {
            $table->dropForeign('scr_sessions_client_fk');
        });

        Schema::table('screening_sessions', function (Blueprint $table) {
            $table->unsignedBigInteger('bioimpedance_client_id')->nullable()->change();
            $table->boolean('is_minor_at_application')->default(false)->after('requested_by_name');
            $table->text('guardian_name')->nullable()->after('is_minor_at_application');
            $table->text('guardian_relationship')->nullable()->after('guardian_name');
            $table->text('guardian_consent_text_snapshot')->nullable()->after('guardian_relationship');
            $table->timestamp('guardian_consent_accepted_at')->nullable()->after('consent_accepted_at');
            $table->timestamp('retention_expires_at')->nullable()->after('expires_at');
            $table->timestamp('anonymized_at')->nullable()->after('retention_expires_at');
            $table->foreign('bioimpedance_client_id', 'scr_sessions_client_fk')
                ->references('id')->on('bioimpedance_clients')->nullOnDelete();
            $table->index(['retention_expires_at', 'anonymized_at'], 'scr_sessions_retention_index');
        });

        $retentionDays = max(1, (int) config('screening.retention_days', 1825));
        DB::table('screening_sessions')
            ->whereNull('retention_expires_at')
            ->orderBy('id')
            ->eachById(function ($session) use ($retentionDays): void {
                $reference = $session->finalized_at ?? $session->created_at;
                DB::table('screening_sessions')->where('id', $session->id)->update([
                    'retention_expires_at' => Carbon::parse($reference)->addDays($retentionDays),
                ]);
            });

        Schema::table('screening_answers', function (Blueprint $table) {
            $table->text('response_label_snapshot')->nullable()->change();
        });

        $this->transformSensitiveColumns(fn (string $value): string => $this->encryptUnlessEncrypted($value));
    }

    public function down(): void
    {
        if (DB::table('screening_sessions')->whereNull('bioimpedance_client_id')->exists()) {
            throw new RuntimeException('A migration não pode ser revertida após anonimizações LGPD, pois o vínculo removido não é recuperável.');
        }

        $this->transformSensitiveColumns(fn (string $value): string => $this->decryptIfEncrypted($value));

        Schema::table('screening_answers', function (Blueprint $table) {
            $table->string('response_label_snapshot')->nullable()->change();
        });

        Schema::table('screening_sessions', function (Blueprint $table) {
            $table->dropIndex('scr_sessions_retention_index');
            $table->dropForeign('scr_sessions_client_fk');
            $table->dropColumn([
                'is_minor_at_application',
                'guardian_name',
                'guardian_relationship',
                'guardian_consent_text_snapshot',
                'guardian_consent_accepted_at',
                'retention_expires_at',
                'anonymized_at',
            ]);
        });

        Schema::table('screening_sessions', function (Blueprint $table) {
            $table->unsignedBigInteger('bioimpedance_client_id')->nullable(false)->change();
            $table->foreign('bioimpedance_client_id', 'scr_sessions_client_fk')
                ->references('id')->on('bioimpedance_clients')->restrictOnDelete();
        });
    }

    private function transformSensitiveColumns(callable $transform): void
    {
        DB::table('screening_answers')->orderBy('id')->eachById(function ($answer) use ($transform): void {
            DB::table('screening_answers')->where('id', $answer->id)->update([
                'question_text_snapshot' => $transform((string) $answer->question_text_snapshot),
                'response_label_snapshot' => $answer->response_label_snapshot === null
                    ? null
                    : $transform((string) $answer->response_label_snapshot),
            ]);
        });

        DB::table('screening_session_reviews')->orderBy('id')->eachById(function ($review) use ($transform): void {
            DB::table('screening_session_reviews')->where('id', $review->id)->update([
                'professional_notes' => $review->professional_notes === null ? null : $transform((string) $review->professional_notes),
                'referral_notes' => $review->referral_notes === null ? null : $transform((string) $review->referral_notes),
            ]);
        });
    }

    private function encryptUnlessEncrypted(string $value): string
    {
        try {
            Crypt::decryptString($value);

            return $value;
        } catch (Throwable) {
            return Crypt::encryptString($value);
        }
    }

    private function decryptIfEncrypted(string $value): string
    {
        try {
            return Crypt::decryptString($value);
        } catch (Throwable) {
            return $value;
        }
    }
};
