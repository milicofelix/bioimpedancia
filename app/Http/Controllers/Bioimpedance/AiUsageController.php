<?php

namespace App\Http\Controllers\Bioimpedance;

use App\Http\Controllers\Controller;
use App\Models\AiUsageRecord;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AiUsageController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        abort_unless($request->user()->isAdmin(), 403, 'Apenas administradores podem consultar os custos.');
        $filters = $request->validate([
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
            'operation' => ['nullable', Rule::in(array_keys(AiUsageRecord::OPERATIONS))],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        if (Carbon::parse($filters['from'])->diffInDays(Carbon::parse($filters['to'])) > 366) {
            throw ValidationException::withMessages(['to' => 'Consulte um período de até um ano por vez.']);
        }
        $query = AiUsageRecord::query()
            ->where('created_at', '>=', Carbon::parse($filters['from'])->startOfDay())
            ->where('created_at', '<', Carbon::parse($filters['to'])->addDay()->startOfDay())
            ->when($filters['operation'] ?? null, fn ($query, $operation) => $query->where('operation', $operation));
        $totals = (clone $query)
            ->selectRaw('COUNT(*) as requests, COUNT(estimated_cost_usd) as priced_requests, SUM(estimated_cost_usd) as estimated_usd, SUM(input_tokens) as input_tokens, SUM(output_tokens) as output_tokens')
            ->selectRaw('COALESCE(SUM(CASE WHEN operation = ? THEN 1 ELSE 0 END), 0) as historical_records', [AiUsageRecord::HISTORICAL_OPENING_OPERATION])
            ->selectRaw('SUM(CASE WHEN operation = ? THEN estimated_cost_usd ELSE 0 END) as historical_usd', [AiUsageRecord::HISTORICAL_OPENING_OPERATION])
            ->first();
        $records = (clone $query)->orderByDesc('id')->paginate(20);
        $rate = filter_var(config('ai_usage.usd_brl_rate'), FILTER_VALIDATE_FLOAT);
        $date = (string) config('ai_usage.exchange_rate_date');

        return response()->json([
            'records' => $records,
            'summary' => [
                'requests' => (int) $totals->requests - (int) $totals->historical_records,
                'unpriced_requests' => (int) $totals->requests - (int) $totals->priced_requests,
                'estimated_usd' => $totals->estimated_usd,
                'historical_records' => (int) $totals->historical_records,
                'historical_usd' => $totals->historical_usd,
                'input_tokens' => (int) $totals->input_tokens,
                'output_tokens' => (int) $totals->output_tokens,
            ],
            'operations' => AiUsageRecord::OPERATIONS,
            'timezone' => config('app.timezone'),
            'conversion' => $rate !== false && $rate > 0 && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)
                ? ['usd_brl_rate' => $rate, 'date' => $date] : null,
        ])->header('Cache-Control', 'private, no-store');
    }
}
