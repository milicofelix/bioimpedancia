<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('screening_sessions', function (Blueprint $table): void {
            $table->date('paper_completed_on')->nullable();
            $table->foreignId('transcribed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('paper_consent_confirmed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('screening_sessions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('transcribed_by_user_id');
            $table->dropColumn(['paper_completed_on', 'paper_consent_confirmed_at']);
        });
    }
};
