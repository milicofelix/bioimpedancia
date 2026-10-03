<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bioimpedance_assessments', function (Blueprint $table) {
            $table->decimal('muscle_rate_percentage', 5, 2)->nullable()->after('skeletal_muscle_percentage');
            $table->decimal('lean_body_mass_kg', 6, 2)->nullable()->after('muscle_rate_percentage');
            $table->decimal('subcutaneous_fat_percentage', 5, 2)->nullable()->after('lean_body_mass_kg');
            $table->decimal('body_water_percentage', 5, 2)->nullable()->after('subcutaneous_fat_percentage');
            $table->decimal('muscle_mass_kg', 6, 2)->nullable()->after('body_water_percentage');
            $table->decimal('bone_mass_kg', 5, 2)->nullable()->after('muscle_mass_kg');
            $table->decimal('protein_percentage', 5, 2)->nullable()->after('bone_mass_kg');
            $table->decimal('fat_mass_kg', 6, 2)->nullable()->after('protein_percentage');
            $table->decimal('water_weight_kg', 6, 2)->nullable()->after('fat_mass_kg');
            $table->decimal('protein_mass_kg', 6, 2)->nullable()->after('water_weight_kg');
            $table->decimal('ideal_body_weight_kg', 6, 2)->nullable()->after('protein_mass_kg');
            $table->string('obesity_level', 100)->nullable()->after('ideal_body_weight_kg');
            $table->string('body_type', 100)->nullable()->after('obesity_level');
        });
    }

    public function down(): void
    {
        Schema::table('bioimpedance_assessments', function (Blueprint $table) {
            $table->dropColumn([
                'muscle_rate_percentage',
                'lean_body_mass_kg',
                'subcutaneous_fat_percentage',
                'body_water_percentage',
                'muscle_mass_kg',
                'bone_mass_kg',
                'protein_percentage',
                'fat_mass_kg',
                'water_weight_kg',
                'protein_mass_kg',
                'ideal_body_weight_kg',
                'obesity_level',
                'body_type',
            ]);
        });
    }
};
