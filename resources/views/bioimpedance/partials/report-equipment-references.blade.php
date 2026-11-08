{{-- Bounded tables avoid DomPDF's automatic header/caption pagination across a long table. --}}
@foreach(collect($referenceTableMetrics)->chunk(5) as $referenceChunk)
<div class="equipment-reference-block">
    <div class="section-title" style="color: {{ $secondaryColor }};">{{ $referenceTableTitle }}{{ $loop->first ? '' : ' - continuação' }}</div>
    <p class="equipment-reference-note">Classificações e limites reproduzem os dados extraídos, sem conversão em diagnóstico clínico.</p>
<table class="equipment-reference-table" aria-label="{{ $referenceTableTitle }}">
    <thead><tr><th scope="col" style="width: 34%;">Indicador</th><th scope="col" style="width: 18%;">Valor apresentado</th><th scope="col" style="width: 20%;">Classificação apresentada</th><th scope="col" style="width: 28%;">Referência informada</th></tr></thead>
    <tbody>
    @foreach($referenceChunk as $metric)
        @php
            $displayLabel = match ($metric['metric_code'] ?? null) {
                'proprietary_obesity_index' => 'Índice de obesidade informado pelo equipamento',
                default => $metric['original_label'],
            };
            $readingIssue = \App\Domain\Bioimpedance\BodyscanMetricIntegrity::readingIssue($metric);
            $rangeIsAmbiguous = ($metric['metric_code'] ?? null) === 'body_fat_percentage' && $hasAmbiguousBodyFatRange;
            $printedRange = $rangeIsAmbiguous
                ? 'Não impressa separadamente'
                : ((($metric['printed_minimum'] ?? null) !== null || ($metric['printed_maximum'] ?? null) !== null)
                    ? $br($metric['printed_minimum'] ?? null, 2).' a '.$br($metric['printed_maximum'] ?? null, 2).' '.($metric['unit'] ?? '')
                    : (($metric['weight_proportion_percentage'] ?? null) !== null ? $br($metric['weight_proportion_percentage'], 1).' % do peso' : 'Não impressa'));
        @endphp
        <tr>
            <td><strong>{{ $displayLabel }}</strong>@if(($metric['metric_code'] ?? null) === 'body_score')<br><span class="muted">Pontuação proprietária; não é nota clínica.</span>@endif @if(($metric['metric_code'] ?? null) === 'proprietary_obesity_index')<br><span class="muted">Valor importado do relatório de origem. A base de cálculo não foi confirmada; não equivale necessariamente à divisão pelo peso-alvo exibido e não foi recalculado.</span>@endif</td>
            <td>
                @if($readingIssue)
                    Conferência de origem necessária
                @elseif(($metric['metric_code'] ?? null) === 'body_balance_assessment' && count($bodyBalanceItems))
                    @foreach($bodyBalanceItems as $balanceItem)<div><strong>{{ $balanceItem['label'] }}:</strong> {{ $balanceItem['value'] }}</div>@endforeach
                @else
                    {{ $extendedMetricDisplayValue($metric) }}
                @endif
            </td>
            <td>{{ $readingIssue ? 'Não validada' : ($metric['application_classification'] ?? 'Não informada') }}</td>
            <td>{{ $readingIssue ? 'Unidade ou proporção incompatível com este indicador. Confira o relatório de origem; o registro original foi preservado.' : $printedRange }}</td>
        </tr>
    @endforeach
    </tbody>
</table>
</div>
@endforeach
