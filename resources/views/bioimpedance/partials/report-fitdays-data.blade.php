@php
    $splitReferencePages = $hasImpedanceData && $fitdaysControlMetrics->isNotEmpty();
    $compositionReferences = $splitReferencePages
        ? $fitdaysReferenceMetrics->whereIn('metric_code', \App\Domain\Bioimpedance\BodyscanExtendedMetricCatalog::CONTEXTUAL_GENERAL_CODES)->values()
        : collect();
    $remainingReferences = $fitdaysReferenceMetrics->reject(fn (array $metric) => $compositionReferences->contains('metric_code', $metric['metric_code']))->values();
@endphp
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

        @if($compositionReferences->isNotEmpty())
            @include('bioimpedance.partials.report-equipment-references', [
                'referenceTableMetrics' => $compositionReferences,
                'referenceTableTitle' => 'Referências de composição corporal',
            ])
        @endif

        @if($splitReferencePages)
    </div>
    <div class="content complementary-references-page">
        @endif

        @if($fitdaysControlMetrics->isNotEmpty())
            <div class="section-title weight-control-title" style="color: {{ $secondaryColor }};">Referências de controle de peso</div>
            <table class="segmental-comparison weight-control-table">
                <caption>Todos os valores abaixo são estimativas proprietárias do equipamento.</caption>
                <thead><tr><th>Item</th><th>Valor apresentado</th></tr></thead>
                <tbody>
                @foreach($fitdaysControlMetrics as $metric)
                    <tr><td><strong>{{ ($metric['metric_code'] ?? null) === 'recommended_target_weight' ? 'Peso-alvo estimado pelo equipamento' : $metric['original_label'] }}</strong></td><td>{{ $extendedMetricDisplayValue($metric, 1) }}</td></tr>
                @endforeach
                </tbody>
            </table>
            <div class="notice weight-control-notice"><strong>Ressalva importante.</strong> Peso-alvo e controles de peso, gordura ou músculo não equivalem a prescrição, meta terapêutica ou recomendação nutricional.</div>
        @endif

        @if($remainingReferences->isNotEmpty())
            @include('bioimpedance.partials.report-equipment-references', [
                'referenceTableMetrics' => $remainingReferences,
                'referenceTableTitle' => 'Referências apresentadas pelo equipamento',
            ])
        @endif

    </div>
</section>
