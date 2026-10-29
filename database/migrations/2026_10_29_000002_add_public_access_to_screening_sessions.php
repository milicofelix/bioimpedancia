<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('screening_sessions', function (Blueprint $table) {
            $table->char('public_token_hash', 64)->nullable()->unique('scr_sessions_public_token_unique');
            $table->string('access_mode', 24)->default('remote');
            $table->timestamp('token_created_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('revoked_by_user_id')->nullable();
            $table->timestamp('identity_verified_at')->nullable();
            $table->unsignedTinyInteger('identity_attempts')->default(0);
            $table->timestamp('last_identity_attempt_at')->nullable();
            $table->timestamp('last_accessed_at')->nullable();
            $table->unsignedInteger('access_count')->default(0);
            $table->timestamp('last_sent_at')->nullable();
            $table->unsignedSmallInteger('send_count')->default(0);

            $table->index(['status', 'expires_at'], 'scr_sessions_status_expiry_index');
            $table->foreign('revoked_by_user_id', 'scr_sessions_revoker_fk')
                ->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('screening_sessions', function (Blueprint $table) {
            $table->dropForeign('scr_sessions_revoker_fk');
            $table->dropIndex('scr_sessions_status_expiry_index');
            $table->dropUnique('scr_sessions_public_token_unique');
            $table->dropColumn([
                'public_token_hash',
                'access_mode',
                'token_created_at',
                'expires_at',
                'revoked_at',
                'revoked_by_user_id',
                'identity_verified_at',
                'identity_attempts',
                'last_identity_attempt_at',
                'last_accessed_at',
                'access_count',
                'last_sent_at',
                'send_count',
            ]);
        });
    }
};
