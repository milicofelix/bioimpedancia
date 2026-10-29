<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('screening_questionnaires', function (Blueprint $table) {
            $table->string('approved_profession', 120)->nullable()->after('approved_by_name');
            $table->json('approval_attestations')->nullable()->after('professional_registration');
            $table->text('approval_notes')->nullable()->after('approval_attestations');
        });
    }

    public function down(): void
    {
        Schema::table('screening_questionnaires', function (Blueprint $table) {
            $table->dropColumn(['approved_profession', 'approval_attestations', 'approval_notes']);
        });
    }
};
