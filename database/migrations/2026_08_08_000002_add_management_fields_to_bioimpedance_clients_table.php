<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bioimpedance_clients', function (Blueprint $table) {
            $table->string('phone_digits', 20)->nullable()->after('phone');
            $table->string('cpf', 20)->nullable()->after('email');
            $table->string('address')->nullable()->after('cpf');
            $table->string('emergency_contact_name')->nullable()->after('address');
            $table->string('emergency_contact_phone')->nullable()->after('emergency_contact_name');
            $table->timestamp('consent_accepted_at')->nullable()->after('emergency_contact_phone');
            $table->date('next_assessment_at')->nullable()->after('consent_accepted_at');
            $table->timestamp('inactivated_at')->nullable()->after('next_assessment_at');

            $table->index('phone_digits');
            $table->index('email');
            $table->index('cpf');
            $table->index('inactivated_at');
        });
    }

    public function down(): void
    {
        Schema::table('bioimpedance_clients', function (Blueprint $table) {
            $table->dropIndex(['phone_digits']);
            $table->dropIndex(['email']);
            $table->dropIndex(['cpf']);
            $table->dropIndex(['inactivated_at']);
            $table->dropColumn([
                'phone_digits',
                'cpf',
                'address',
                'emergency_contact_name',
                'emergency_contact_phone',
                'consent_accepted_at',
                'next_assessment_at',
                'inactivated_at',
            ]);
        });
    }
};
