<?php

use App\Models\Bioimpedance\BioimpedanceAssessment;
use App\Services\Bioimpedance\LegacyBioimpedanceAnalysisRefresher;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $refresher = app(LegacyBioimpedanceAnalysisRefresher::class);

        BioimpedanceAssessment::query()
            ->with('client')
            ->orderBy('id')
            ->each(fn (BioimpedanceAssessment $assessment) => $refresher->refreshIfLegacy($assessment));
    }

    public function down(): void
    {
        //
    }
};
