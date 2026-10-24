<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bioimpedance_knowledge_documents', function (Blueprint $table) {
            $table->string('status', 32)->default('draft')->after('source_url')->index();
            $table->unsignedBigInteger('created_by_user_id')->nullable()->after('status');
            $table->unsignedBigInteger('reviewed_by_user_id')->nullable()->after('approved_at');
            $table->timestamp('reviewed_at')->nullable()->after('reviewed_by_user_id');
            $table->unsignedBigInteger('published_by_user_id')->nullable()->after('reviewed_at');
            $table->timestamp('published_at')->nullable()->after('published_by_user_id');
            $table->unsignedBigInteger('superseded_by_document_id')->nullable()->after('published_at');
            $table->timestamp('superseded_at')->nullable()->after('superseded_by_document_id');
            $table->foreign('superseded_by_document_id', 'bio_knowledge_docs_superseded_fk')
                ->references('id')->on('bioimpedance_knowledge_documents')->nullOnDelete();
            $table->foreign('reviewed_by_user_id', 'bio_knowledge_docs_reviewer_fk')
                ->references('id')->on('users')->nullOnDelete();
            $table->foreign('published_by_user_id', 'bio_knowledge_docs_publisher_fk')
                ->references('id')->on('users')->nullOnDelete();
            $table->foreign('created_by_user_id', 'bio_knowledge_docs_creator_fk')
                ->references('id')->on('users')->nullOnDelete();
        });

        DB::table('bioimpedance_knowledge_documents')
            ->where('approved', true)
            ->update([
                'status' => 'published',
                'reviewed_at' => DB::raw('COALESCE(approved_at, updated_at)'),
                'published_at' => DB::raw('COALESCE(approved_at, updated_at)'),
            ]);

        Schema::table('bioimpedance_ai_analysis_sources', function (Blueprint $table) {
            $table->json('source_snapshot')->nullable()->after('relevance_score');
        });
    }

    public function down(): void
    {
        Schema::table('bioimpedance_ai_analysis_sources', function (Blueprint $table) {
            $table->dropColumn('source_snapshot');
        });

        Schema::table('bioimpedance_knowledge_documents', function (Blueprint $table) {
            $table->dropForeign('bio_knowledge_docs_superseded_fk');
            $table->dropForeign('bio_knowledge_docs_reviewer_fk');
            $table->dropForeign('bio_knowledge_docs_publisher_fk');
            $table->dropForeign('bio_knowledge_docs_creator_fk');
            $table->dropColumn(['status', 'created_by_user_id', 'reviewed_by_user_id', 'reviewed_at', 'published_by_user_id', 'published_at', 'superseded_by_document_id', 'superseded_at']);
        });
    }
};
