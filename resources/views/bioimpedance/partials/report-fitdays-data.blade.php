<section class="report-section">
    @include('bioimpedance.partials.report-header', ['pageTitle' => 'Indicadores complementares', 'pageNumber' => $pageNumber, 'totalSections' => $totalSections])
    <div class="content">
        <div class="notice"><strong>Como interpretar.</strong> Os valores, faixas e classificações desta página são estimativas apresentadas pelo equipamento. A interpretação profissional considera histórico, condições da medição e contexto individual.</div>

        @if($extendedMetricsByCode->has('waist_hip_ratio') || $extendedMetricsByCode->has('asmi'))
            <div class="section-title" style="color: {{ $secondaryColor }};">Índices do equipamento</div>
            <table class="grid"><tr>
                @if($extendedMetricsByCode->has('waist_hip_ratio'))
                    <td class="box" style="width: 50%;"><div class="label">WHR · relação cintura-quadril</div><div class="metric-small">{{ $extendedMetricDisplayValue($extendedMetricsByCode->get('waist_hip_ratio')) }}</div><div class="muted">Índice estimado pelo equipamento; não constitui diagnóstico isolado.</div></td>
                @endif
                @if($extendedMetricsByCode->has('asmi'))
                    <td class="box" style="width: 50%;"><div class="label">ASMI</div><div class="metric-small">{{ $extendedMetricDisplayValue($extendedMetricsByCode->get('asmi')) }}</div><div class="muted">Índice estimado pelo equipamento; não substitui avaliação clínica ou funcional.</div></td>
                @endif
            </tr></table>
        @endif

        @if($hasImpedanceData)
            @include('bioimpedance.partials.report-fitdays-impedance', ['embedded' => true])
        @endif

        @if($fitdaysControlMetrics->isNotEmpty())
            <div class="section-title weight-control-title" style="color: {{ $secondaryColor }};">Referências de controle de peso</div>
            <table class="segmental-comparison weight-control-table">
                <caption>Todos os valores abaixo são estimativas proprietárias do equipamento.</caption>
                <thead><tr><th>Item</th><th>Valor apresentado</th></tr></thead>
                <tbody>
                @foreach($fitdaysControlMetrics as $metric)
                    <tr><td><strong>{{ $metric['original_label'] }}</strong></td><td>{{ $extendedMetricDisplayValue($metric, 1) }}</td></tr>
                @endforeach
                </tbody>
            </table>
            <div class="notice weight-control-notice"><strong>Ressalva importante.</strong> Peso-alvo e controles de peso, gordura ou músculo não equivalem a prescrição, meta terapêutica ou recomendação nutricional.</div>
        @endif

        @if($fitdaysReferenceMetrics->isNotEmpty())
            <div class="section-title" style="color: {{ $secondaryColor }};">Referências apresentadas pelo equipamento</div>
            <table class="segmental-comparison">
                <caption>Classificações e limites reproduzem os dados extraídos, sem conversão em diagnóstico clínico.</caption>
                <thead><tr><th>Indicador</th><th>Valor apresentado</th><th>Classificação apresentada</th><th>Referência informada</th></tr></thead>
                <tbody>
                @foreach($fitdaysReferenceMetrics as $metric)
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
        @endif

    </div>
</section>
