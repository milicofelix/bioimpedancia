<?php

namespace Database\Seeders;

use App\Models\AiUsageRecord;
use Illuminate\Database\Seeder;

class AiUsageHistoricalOpeningSeeder extends Seeder
{
    public const IMPORT_REFERENCE = 'vita-scan-historical-usd-1-50-confirmed-2026-11-08';

    public function run(): void
    {
        // Explicit opening amount confirmed by the administrator, not reconstructed usage.
        // A unique reference also prevents duplication by concurrent invocations.
        $record = AiUsageRecord::query()->firstOrCreate([
            'import_reference' => self::IMPORT_REFERENCE,
        ], [
            'operation' => AiUsageRecord::HISTORICAL_OPENING_OPERATION,
            'model' => 'not_recorded',
            'service_tier' => null,
            'outcome' => 'historical_import',
            'http_status' => null,
            // The existing schema requires a duration; not presented as request timing.
            'duration_ms' => 0,
            'input_tokens' => null,
            'output_tokens' => null,
            'cached_tokens' => null,
            'reasoning_tokens' => null,
            'cache_write_30m_tokens' => null,
            'cache_write_12h_tokens' => null,
            'cost_status' => 'historical_reported',
            'estimated_cost_usd' => '1.500000000000',
            'price_snapshot' => null,
            // This is the import date, NOT an invented historical consumption date.
            'created_at' => now(),
        ]);

        $this->command?->info($record->wasRecentlyCreated
            ? 'Consumo histórico de US$ 1,50 incluído. Nenhuma requisição ou token foi reconstruído.'
            : 'Consumo histórico já registrado. Nenhum valor ou data foi alterado.');
    }
}
