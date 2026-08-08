<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bioimpedance_report_shares', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bioimpedance_assessment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('token_hash', 64)->unique();
            $table->string('channel', 32)->default('whatsapp');
            $table->string('recipient')->nullable();
            $table->text('message')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamp('viewed_at')->nullable();
            $table->unsignedInteger('view_count')->default(0);
            $table->string('last_viewed_ip', 45)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bioimpedance_report_shares');
    }
};
