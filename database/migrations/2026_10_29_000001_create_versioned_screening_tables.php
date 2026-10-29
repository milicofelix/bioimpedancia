<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('screening_questionnaires', function (Blueprint $table) {
            $table->id();
            $table->string('code', 60);
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('version', 60);
            $table->string('status', 40)->default('draft');
            $table->unsignedSmallInteger('recall_period_days')->nullable();
            $table->text('consent_text');
            $table->text('disclaimer');
            $table->text('professional_review_notice')->nullable();
            $table->json('reference_sources')->nullable();
            $table->foreignId('approved_by_user_id')->nullable();
            $table->string('approved_by_name')->nullable();
            $table->string('professional_registration', 80)->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->unique(['code', 'version'], 'scr_questionnaires_code_version_unique');
            $table->index('status');
            $table->foreign('approved_by_user_id', 'scr_questionnaires_approver_fk')
                ->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('screening_questions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('screening_questionnaire_id');
            $table->string('code', 60);
            $table->unsignedSmallInteger('position');
            $table->text('text');
            $table->string('response_type', 40)->default('single_choice');
            $table->boolean('requires_individual_attention')->default(false);
            $table->timestamps();

            $table->unique(['screening_questionnaire_id', 'code'], 'scr_questions_questionnaire_code_unique');
            $table->unique(['screening_questionnaire_id', 'position'], 'scr_questions_questionnaire_position_unique');
            $table->foreign('screening_questionnaire_id', 'scr_questions_questionnaire_fk')
                ->references('id')->on('screening_questionnaires')->restrictOnDelete();
        });

        Schema::create('screening_options', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('screening_question_id');
            $table->string('label');
            $table->smallInteger('value');
            $table->unsignedSmallInteger('position');
            $table->timestamps();

            $table->unique(['screening_question_id', 'value'], 'scr_options_question_value_unique');
            $table->unique(['screening_question_id', 'position'], 'scr_options_question_position_unique');
            $table->foreign('screening_question_id', 'scr_options_question_fk')
                ->references('id')->on('screening_questions')->restrictOnDelete();
        });

        Schema::create('screening_ranges', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('screening_questionnaire_id');
            $table->unsignedSmallInteger('position');
            $table->smallInteger('minimum_score');
            $table->smallInteger('maximum_score');
            $table->string('description');
            $table->text('display_message');
            $table->timestamps();

            $table->unique(['screening_questionnaire_id', 'position'], 'scr_ranges_questionnaire_position_unique');
            $table->foreign('screening_questionnaire_id', 'scr_ranges_questionnaire_fk')
                ->references('id')->on('screening_questionnaires')->restrictOnDelete();
        });

        Schema::create('screening_sessions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('bioimpedance_client_id');
            $table->unsignedBigInteger('screening_questionnaire_id');
            $table->unsignedBigInteger('requested_by_user_id')->nullable();
            $table->unsignedBigInteger('supersedes_session_id')->nullable();
            $table->unsignedBigInteger('cancelled_by_user_id')->nullable();
            $table->string('status', 32)->default('pending');
            $table->string('questionnaire_code', 60);
            $table->string('questionnaire_version', 60);
            $table->string('questionnaire_name');
            $table->string('requested_by_name')->nullable();
            $table->text('consent_text_snapshot');
            $table->text('disclaimer_snapshot');
            $table->timestamp('consent_accepted_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finalized_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->text('correction_reason')->nullable();
            $table->smallInteger('total_score')->nullable();
            $table->unsignedBigInteger('screening_range_id')->nullable();
            $table->string('result_description')->nullable();
            $table->text('result_message')->nullable();
            $table->timestamps();

            $table->index(['bioimpedance_client_id', 'created_at'], 'scr_sessions_client_created_index');
            $table->index(['status', 'created_at'], 'scr_sessions_status_created_index');
            $table->foreign('bioimpedance_client_id', 'scr_sessions_client_fk')
                ->references('id')->on('bioimpedance_clients')->restrictOnDelete();
            $table->foreign('screening_questionnaire_id', 'scr_sessions_questionnaire_fk')
                ->references('id')->on('screening_questionnaires')->restrictOnDelete();
            $table->foreign('requested_by_user_id', 'scr_sessions_requester_fk')
                ->references('id')->on('users')->nullOnDelete();
            $table->foreign('supersedes_session_id', 'scr_sessions_supersedes_fk')
                ->references('id')->on('screening_sessions')->restrictOnDelete();
            $table->foreign('cancelled_by_user_id', 'scr_sessions_canceller_fk')
                ->references('id')->on('users')->nullOnDelete();
            $table->foreign('screening_range_id', 'scr_sessions_range_fk')
                ->references('id')->on('screening_ranges')->restrictOnDelete();
        });

        Schema::create('screening_answers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('screening_session_id');
            $table->unsignedBigInteger('screening_question_id');
            $table->unsignedBigInteger('screening_option_id')->nullable();
            $table->string('question_code', 60);
            $table->string('questionnaire_version', 60);
            $table->text('question_text_snapshot');
            $table->string('response_label_snapshot')->nullable();
            $table->smallInteger('response_value')->nullable();
            $table->smallInteger('score');
            $table->boolean('requires_individual_attention')->default(false);
            $table->timestamps();

            $table->unique(['screening_session_id', 'screening_question_id'], 'scr_answers_session_question_unique');
            $table->foreign('screening_session_id', 'scr_answers_session_fk')
                ->references('id')->on('screening_sessions')->restrictOnDelete();
            $table->foreign('screening_question_id', 'scr_answers_question_fk')
                ->references('id')->on('screening_questions')->restrictOnDelete();
            $table->foreign('screening_option_id', 'scr_answers_option_fk')
                ->references('id')->on('screening_options')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('screening_answers');
        Schema::dropIfExists('screening_sessions');
        Schema::dropIfExists('screening_ranges');
        Schema::dropIfExists('screening_options');
        Schema::dropIfExists('screening_questions');
        Schema::dropIfExists('screening_questionnaires');
    }
};
