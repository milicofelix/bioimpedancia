<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('screening_sessions', function (Blueprint $table) {
            $table->string('document_authenticity_code', 32)->nullable()->after('attention_notice_snapshot');
        });

        DB::table('screening_sessions')
            ->where('status', 'finalized')
            ->whereNull('document_authenticity_code')
            ->orderBy('id')
            ->eachById(function ($session): void {
                DB::table('screening_sessions')
                    ->where('id', $session->id)
                    ->update(['document_authenticity_code' => 'VS-'.Str::upper(Str::random(20))]);
            });

        Schema::table('screening_sessions', function (Blueprint $table) {
            $table->unique('document_authenticity_code', 'scr_sessions_document_code_unique');
        });

        Schema::create('screening_session_reviews', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('screening_session_id');
            $table->text('professional_notes')->nullable();
            $table->text('referral_notes')->nullable();
            $table->unsignedBigInteger('updated_by_user_id');
            $table->timestamps();

            $table->unique('screening_session_id', 'scr_session_reviews_session_unique');
            $table->foreign('screening_session_id', 'scr_session_reviews_session_fk')
                ->references('id')->on('screening_sessions')->restrictOnDelete();
            $table->foreign('updated_by_user_id', 'scr_session_reviews_updater_fk')
                ->references('id')->on('users')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('screening_session_reviews');

        Schema::table('screening_sessions', function (Blueprint $table) {
            $table->dropUnique('scr_sessions_document_code_unique');
            $table->dropColumn('document_authenticity_code');
        });
    }
};
