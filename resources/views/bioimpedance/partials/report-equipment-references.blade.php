<div class="section-title" style="color: {{ $secondaryColor }};">{{ $referenceTableTitle }}</div>
<table class="segmental-comparison">
    <caption>Classificações e limites reproduzem os dados extraídos, sem conversão em diagnóstico clínico.</caption>
    <thead><tr><th>Indicador</th><th>Valor apresentado</th><th>Classificação apresentada</th><th>Referência informada</th></tr></thead>
    <tbody>
    @foreach($referenceTableMetrics as $metric)
        @php
            $displayLabel = match ($metric['metric_code'] ?? null) {
                'proprietary_obesity_index' => 'Relação entre peso atual e peso-alvo do equipamento',
                default => $metric['original_label'],
            };
            $rangeIsAmbiguous = ($metric['metric_code'] ?? null) === 'body_fat_percentage' && $hasAmbiguousBodyFatRange;
            $printedRange = $rangeIsAmbiguous
                ? 'Não impressa separadamente'
                : ((($metric['printed_minimum'] ?? null) !== null || ($metric['printed_maximum'] ?? null) !== null)
                    ? $br($metric['printed_minimum'] ?? null, 2).' a '.$br($metric['printed_maximum'] ?? null, 2).' '.($metric['unit'] ?? '')
                    : (($metric['weight_proportion_percentage'] ?? null) !== null ? $br($metric['weight_proportion_percentage'], 1).' % do peso' : 'Não impressa'));
        @endphp
        <tr>
            <td><strong>{{ $displayLabel }}</strong>@if(($metric['metric_code'] ?? null) === 'body_score')<br><span class="muted">Pontuação proprietária; não é nota clínica.</span>@endif</td>
            <td>
                @if(($metric['metric_code'] ?? null) === 'body_balance_assessment' && count($bodyBalanceItems))
                    @foreach($bodyBalanceItems as $balanceItem)<div><strong>{{ $balanceItem['label'] }}:</strong> {{ $balanceItem['value'] }}</div>@endforeach
                @else
                    {{ $extendedMetricDisplayValue($metric) }}
                @endif
            </td>
            <td>{{ $metric['application_classification'] ?? 'Não informada' }}</td>
            <td>{{ $printedRange }}</td>
        </tr>
    @endforeach
    </tbody>
</table>
