<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bioimpedance_clients', function (Blueprint $table) {
            $table->id();
            $table->string('full_name');
            $table->date('birth_date');
            $table->string('biological_sex', 16);
            $table->decimal('height_cm', 5, 2);
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('full_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bioimpedance_clients');
    }
};
