<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('bioimpedance_ai_analysis_outputs');
        Schema::dropIfExists('bioimpedance_ai_analysis_sources');
        Schema::dropIfExists('bioimpedance_ai_analysis_requests');
        Schema::dropIfExists('bioimpedance_knowledge_chunks');
        Schema::dropIfExists('bioimpedance_knowledge_documents');

        Schema::create('bioimpedance_knowledge_documents', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('document_type', 64);
            $table->string('manufacturer')->nullable();
            $table->string('device_model')->nullable();
            $table->string('version')->nullable();
            $table->string('source_url')->nullable();
            $table->boolean('approved')->default(false);
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('bioimpedance_knowledge_chunks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('bioimpedance_knowledge_document_id');
            $table->string('chunk_key')->unique();
            $table->string('section');
            $table->unsignedSmallInteger('page')->nullable();
            $table->string('language', 16)->default('pt-BR');
            $table->json('tags')->nullable();
            $table->text('content');
            $table->string('checksum', 64);
            $table->timestamps();

            $table->index(['language', 'chunk_key']);
            $table->foreign('bioimpedance_knowledge_document_id', 'bio_knowledge_chunks_document_fk')
                ->references('id')
                ->on('bioimpedance_knowledge_documents')
                ->cascadeOnDelete();
        });

        Schema::create('bioimpedance_ai_analysis_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('bioimpedance_assessment_id');
            $table->unsignedBigInteger('requested_by_user_id')->nullable();
            $table->string('prompt_version', 32);
            $table->string('reference_version', 32)->nullable();
            $table->string('model_name')->nullable();
            $table->string('status', 40);
            $table->json('structured_context');
            $table->json('validation_errors')->nullable();
            $table->timestamps();

            $table->foreign('bioimpedance_assessment_id', 'bio_ai_requests_assessment_fk')
                ->references('id')
                ->on('bioimpedance_assessments')
                ->cascadeOnDelete();
            $table->foreign('requested_by_user_id', 'bio_ai_requests_user_fk')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });

        Schema::create('bioimpedance_ai_analysis_sources', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('bioimpedance_ai_analysis_request_id');
            $table->unsignedBigInteger('bioimpedance_knowledge_chunk_id');
            $table->unsignedSmallInteger('relevance_score')->default(0);
            $table->timestamps();

            $table->foreign('bioimpedance_ai_analysis_request_id', 'bio_ai_sources_request_fk')
                ->references('id')
                ->on('bioimpedance_ai_analysis_requests')
                ->cascadeOnDelete();
            $table->foreign('bioimpedance_knowledge_chunk_id', 'bio_ai_sources_chunk_fk')
                ->references('id')
                ->on('bioimpedance_knowledge_chunks')
                ->cascadeOnDelete();
        });

        Schema::create('bioimpedance_ai_analysis_outputs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('bioimpedance_ai_analysis_request_id');
            $table->json('raw_response');
            $table->text('professional_observation')->nullable();
            $table->string('validation_status', 40);
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->foreign('bioimpedance_ai_analysis_request_id', 'bio_ai_outputs_request_fk')
                ->references('id')
                ->on('bioimpedance_ai_analysis_requests')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bioimpedance_ai_analysis_outputs');
        Schema::dropIfExists('bioimpedance_ai_analysis_sources');
        Schema::dropIfExists('bioimpedance_ai_analysis_requests');
        Schema::dropIfExists('bioimpedance_knowledge_chunks');
        Schema::dropIfExists('bioimpedance_knowledge_documents');
    }
};
