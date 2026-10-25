<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Relatório de Bioimpedância</title>
    <style>
        @page { margin: 76px 18px 42px; }
        * { box-sizing: border-box; }
        body { margin: 0; color: #172033; font-family: DejaVu Sans, Arial, sans-serif; font-size: 9px; line-height: 1.4; }
        .document-header { position: fixed; top: -63px; left: 0; right: 0; height: 52px; padding: 6px 12px; border-bottom: 1px solid #ead5da; background: #fff; }
        .document-brand, .document-identification { display: inline-block; width: 49%; vertical-align: middle; }
        .document-logo { width: 150px; max-height: 40px; object-fit: contain; }
        .document-brand-name { font-size: 16px; }
        .document-identification { color: #667085; font-size: 7px; text-align: right; }
        .document-identification strong { color: #172033; font-size: 9px; }
        .document-footer { position: fixed; bottom: -31px; left: 0; right: 0; height: 24px; padding: 6px 12px; border-top: 1px solid #ead5da; color: #667085; font-size: 7px; background: #fff; }
        .footer-right { float: right; }
        .report-section { page-break-before: always; }
        .report-section-first { page-break-before: auto; }
        .section-header { padding: 10px 18px 9px; border: 1px solid #f2c7cf; border-bottom: 2px solid #d88b9a; border-radius: 8px 8px 0 0; background: #fbf2f4; page-break-after: avoid; }
        .section-heading h1 { margin: 2px 0 3px; font-size: 17px; }
        .eyebrow, .label, .section-title { font-size: 7px; font-weight: 700; letter-spacing: .5px; text-transform: uppercase; }
        .content { padding: 12px 18px; border-right: 1px solid #f2c7cf; border-bottom: 1px solid #f2c7cf; border-left: 1px solid #f2c7cf; background: #fffafa; }
        .section-title { margin: 12px 0 6px; font-size: 10px; letter-spacing: .7px; page-break-after: avoid; }
        .grid { width: 100%; border-collapse: separate; border-spacing: 6px; }
        .grid tr { page-break-inside: avoid; }
        .box { padding: 9px; border: 1px solid #f2d6dc; border-radius: 8px; background: #fff; vertical-align: top; page-break-inside: avoid; }
        .hero { background: #f8e8eb; }
        .client-name { margin-top: 3px; font-size: 16px; font-weight: 700; }
        .metric-value { margin-top: 5px; color: #101828; font-size: 23px; font-weight: 700; line-height: 1.05; }
        .metric-unit { color: #667085; font-size: 9px; }
        .metric-small { margin-top: 4px; font-size: 13px; font-weight: 700; }
        .muted { color: #667085; }
        .badge { display: inline-block; margin-top: 6px; padding: 4px 7px; border-radius: 10px; font-size: 7px; font-weight: 700; text-transform: uppercase; }
        .good { background: #dff7ea; color: #087443; }
        .warning { background: #fff1c2; color: #805600; }
        .danger { background: #ffe4e8; color: #9f1c13; }
        .attention { background: #e0f2fe; color: #075985; }
        .neutral, .pending { background: #f2f4f7; color: #475467; }
        .summary, .notice, .guidance { margin-top: 8px; padding: 10px 12px; border-radius: 8px; }
        .summary { border: 1px solid #f2d6dc; background: #fff; }
        .notice { border-left: 3px solid #d88b9a; background: #fff4f6; }
        .guidance { border: 1px solid #b7e3dc; background: #f1fbf8; }
        .guidance-section { margin-top: 5px; padding: 7px 10px; border: 1px solid #b7e3dc; border-radius: 7px; background: #f7fcfa; page-break-inside: auto; }
        .guidance-section-title { margin-bottom: 3px; color: #176b5b; font-size: 8px; font-weight: 700; page-break-after: avoid; }
        .origin-note { margin-top: 5px; padding: 5px 7px; border-left: 3px solid #d88b9a; background: #fff7f8; color: #475467; font-size: 7px; }
        .canceled { margin-bottom: 8px; padding: 8px; border: 1px solid #fecdd3; border-radius: 8px; background: #fff1f2; color: #9f1239; }
        .scale { width: 100%; height: 7px; margin-top: 7px; border-radius: 3px; overflow: hidden; background: #e5e7eb; }
        .segment { height: 7px; float: left; }
        .blue { background: #3b82f6; } .emerald { background: #10b981; } .amber { background: #f59e0b; } .rose { background: #f43f5e; } .slate { background: #cbd5e1; }
        .pointer-row { position: relative; height: 9px; }
        .pointer { position: absolute; top: 0; width: 0; height: 0; margin-left: -4px; border-left: 4px solid transparent; border-right: 4px solid transparent; border-top: 7px solid #111827; }
        .legend { width: 100%; border-collapse: collapse; margin-top: 2px; }
        .legend td { color: #667085; font-size: 6px; text-align: center; }
        .trend-grid { width: 100%; border-collapse: separate; border-spacing: 5px; table-layout: fixed; }
        .trend-card { padding: 7px; border: 1px solid #e4e7ec; border-radius: 7px; background: #fff; vertical-align: top; page-break-inside: avoid; }
        .trend-current { margin-top: 2px; font-size: 12px; font-weight: 700; }
        .trend-comparison { margin-top: 4px; color: #475467; font-size: 7px; }
        .trend-comparison strong { color: #172033; }
        .trend-chart { display: block; width: 100%; height: 62px; margin-top: 3px; }
        .trend-dates { width: 100%; color: #667085; font-size: 6px; }
        .trend-dates td:last-child { text-align: right; }
        .source-list { margin: 5px 0 0; padding-left: 16px; }
        .source-list li { margin-bottom: 4px; page-break-inside: avoid; }
        .validation { width: 100%; border-collapse: collapse; margin-top: 8px; }
        .validation tr { page-break-inside: avoid; }
        .validation td { padding: 7px 8px; border: 1px solid #e5e7eb; background: #fff; vertical-align: top; }
        .keep-together { page-break-inside: avoid; }
        .page-guidance .content { padding-top: 10px; padding-bottom: 8px; }
        .page-guidance .section-title { margin-top: 8px; margin-bottom: 4px; }
        .page-guidance .summary, .page-guidance .notice, .page-guidance .guidance { margin-top: 5px; padding: 7px 10px; }
        .page-guidance .summary p, .page-guidance .notice p, .page-guidance .guidance p { margin-top: 3px; margin-bottom: 3px; }
        .page-guidance .validation { margin-top: 5px; }
        .page-guidance .validation td { padding: 5px 7px; }
    </style>
</head>
<body>
@php
    $indicators = $assessment['analysis']['indicators'] ?? [];
    $bmi = $indicators['bmi'] ?? [];
    $bodyFat = $indicators['body_fat'] ?? [];
    $muscle = $indicators['skeletal_muscle'] ?? [];
    $visceral = $indicators['visceral_fat'] ?? [];
    $reference = $assessment['analysis']['reference'] ?? [];
    $assessmentAge = $assessment['age_at_assessment'] ?? $client['age'];
    $assessmentHeight = $assessment['height_cm_at_assessment'] ?? $client['height_cm'];
    $assessmentSex = $assessment['biological_sex_at_assessment'] ?? $client['biological_sex'];
    $sexLabels = ['female' => 'Feminino', 'male' => 'Masculino'];
    $tone = fn ($indicator) => $indicator['tone'] ?? 'neutral';
    $br = fn ($value, $decimals = 1) => $value === null || $value === '' ? '-' : number_format((float) $value, $decimals, ',', '.');
    $primaryColor = $clinic['primary_color'] ?? '#d88b9a';
    $secondaryColor = $clinic['secondary_color'] ?? '#4a4a4a';
    $isRelaxmedic = ($assessment['device_model'] ?? null) === 'Relaxmedic';
    $manufacturer = trim((string) ($reference['manufacturer'] ?? ($isRelaxmedic ? 'Relaxmedic' : 'Omron')));
    $deviceModel = trim((string) ($reference['model'] ?? $assessment['device_model'] ?? ''));
    $genericDeviceModels = ['relaxmedic', 'relaxmedic/relaxfit'];
    $hasSpecificDeviceModel = $deviceModel !== ''
        && mb_strtolower($deviceModel) !== mb_strtolower($manufacturer)
        && ! in_array(mb_strtolower($deviceModel), $genericDeviceModels, true);
    $displayClassification = fn ($indicator, $fallback = '-') => ($indicator['pending'] ?? false)
        ? 'Sem classificação disponível'
        : ($indicator['classification'] ?? $fallback);
    $executiveSummary = str_replace(
        'a referência de classificação Relaxmedic não está cadastrada',
        'sem classificação disponível para este indicador',
        $assessment['analysis']['summary'] ?? 'Síntese indisponível.',
    );
    $observationOriginLabels = [
        'professional' => 'Interpretação redigida pelo profissional responsável.',
        'ai_approved' => 'Sugestão gerada pela IA e aprovada pelo profissional sem alterações.',
        'ai_edited_and_approved' => 'Sugestão gerada pela IA, editada e aprovada pelo profissional.',
        'none' => 'Nenhuma interpretação profissional registrada.',
    ];
    $guidanceTitles = [
        'Contexto da avaliação',
        'Alimentação',
        'Movimento e função',
        'Acompanhamento',
        'Cuidados importantes',
    ];
    $guidanceSections = collect(preg_split('/\R{2,}/u', trim((string) ($assessment['care_guidance'] ?? ''))) ?: [])
        ->filter()
        ->map(function (string $paragraph) use ($guidanceTitles): array {
            foreach ($guidanceTitles as $title) {
                if (str_starts_with($paragraph, $title.':')) {
                    return ['title' => $title, 'text' => trim(mb_substr($paragraph, mb_strlen($title) + 1))];
                }
            }

            return ['title' => 'Orientação', 'text' => trim($paragraph)];
        })
        ->values();
    $relaxmedicMetrics = array_filter([
        'Taxa muscular' => isset($assessment['muscle_rate_percentage']) ? $br($assessment['muscle_rate_percentage']).' %' : null,
        'Massa corporal magra' => isset($assessment['lean_body_mass_kg']) ? $br($assessment['lean_body_mass_kg']).' kg' : null,
        'Gordura subcutânea' => isset($assessment['subcutaneous_fat_percentage']) ? $br($assessment['subcutaneous_fat_percentage']).' %' : null,
        'Água corporal' => isset($assessment['body_water_percentage']) ? $br($assessment['body_water_percentage']).' %' : null,
        'Massa muscular' => isset($assessment['muscle_mass_kg']) ? $br($assessment['muscle_mass_kg']).' kg' : null,
        'Massa óssea' => isset($assessment['bone_mass_kg']) ? $br($assessment['bone_mass_kg']).' kg' : null,
        'Proteína' => isset($assessment['protein_percentage']) ? $br($assessment['protein_percentage']).' %' : null,
        'Massa gorda' => isset($assessment['fat_mass_kg']) ? $br($assessment['fat_mass_kg']).' kg' : null,
        'Peso da água' => isset($assessment['water_weight_kg']) ? $br($assessment['water_weight_kg']).' kg' : null,
        'Massa de proteína' => isset($assessment['protein_mass_kg']) ? $br($assessment['protein_mass_kg']).' kg' : null,
        'Peso corporal ideal' => isset($assessment['ideal_body_weight_kg']) ? $br($assessment['ideal_body_weight_kg']).' kg' : null,
        'Nível de obesidade' => $assessment['obesity_level'] ?? null,
        'Tipo de corpo' => $assessment['body_type'] ?? null,
    ], fn ($value) => $value !== null && $value !== '');
    $metricNotes = [
        'Massa corporal magra' => 'Estimativa do equipamento; não comprova força ou função.',
        'Massa muscular' => 'Estimativa; não substitui avaliação funcional.',
        'Massa óssea' => 'Não corresponde a densitometria óssea.',
        'Proteína' => 'Não identifica ingestão ou deficiência nutricional.',
        'Massa de proteína' => 'Não identifica deficiência nutricional.',
        'Peso corporal ideal' => 'Referência do fabricante, não uma meta prescrita.',
        'Nível de obesidade' => 'Origem: RelaxFit. Classificação importada, normalizada e revisada.',
        'Tipo de corpo' => 'Origem: RelaxFit. Classificação proprietária importada da imagem.',
    ];
@endphp

@include('bioimpedance.partials.report-document-header')
@include('bioimpedance.partials.report-footer')

<section class="report-section report-section-first">
    @include('bioimpedance.partials.report-header', ['pageTitle' => 'Visão executiva', 'pageNumber' => 1])
    <div class="content">
        @if($assessment['is_canceled'])<div class="canceled"><strong>Avaliação cancelada.</strong> {{ $assessment['cancellation_reason'] }}</div>@endif
        <table class="grid"><tr>
            <td class="box" style="width: 46%;"><div class="label" style="color: {{ $primaryColor }};">Cliente</div><div class="client-name">{{ $client['full_name'] }}</div><div class="muted">Avaliado em {{ \Illuminate\Support\Carbon::parse($assessment['evaluated_at'])->format('d/m/Y H:i') }}</div></td>
            <td class="box"><div class="label">Idade</div><div class="metric-small">{{ $assessmentAge }} anos</div></td>
            <td class="box"><div class="label">Sexo</div><div class="metric-small">{{ $sexLabels[$assessmentSex] ?? '-' }}</div></td>
            <td class="box"><div class="label">Altura</div><div class="metric-small">{{ $br($assessmentHeight, 0) }} cm</div></td>
        </tr></table>
        <div class="section-title" style="color: {{ $secondaryColor }};">Resultados principais</div>
        <table class="grid"><tr>
            <td class="box hero" style="width: 25%;"><div class="label">Peso</div><div class="metric-value">{{ $br($assessment['weight_kg']) }} <span class="metric-unit">kg</span></div></td>
            <td class="box hero" style="width: 25%;"><div class="label">IMC calculado</div><div class="metric-value">{{ $br($assessment['calculated_bmi']) }}</div><span class="badge {{ $tone($bmi) }}">{{ $displayClassification($bmi) }}</span></td>
            <td class="box hero" style="width: 25%;"><div class="label">Gordura corporal</div><div class="metric-value">{{ $br($assessment['body_fat_percentage']) }} <span class="metric-unit">%</span></div><span class="badge {{ $tone($bodyFat) }}">{{ $displayClassification($bodyFat, 'Estimativa') }}</span></td>
            <td class="box hero" style="width: 25%;"><div class="label">Músculo esquelético</div><div class="metric-value">{{ $br($assessment['skeletal_muscle_percentage']) }} <span class="metric-unit">%</span></div><span class="badge {{ $tone($muscle) }}">{{ $displayClassification($muscle, 'Estimativa') }}</span></td>
        </tr></table>
        <div class="summary"><div class="section-title" style="margin-top: 0; color: {{ $primaryColor }};">Síntese da avaliação</div><p>{{ $executiveSummary }}</p></div>
        @if($isRelaxmedic)
            <div class="notice"><strong>Como interpretar este relatório.</strong> Os resultados Relaxmedic são estimativas e índices proprietários do equipamento. A coerência matemática entre medidas não comprova precisão fisiológica e não substitui avaliação clínica ou funcional.</div>
            @if($assessmentAge >= 60)<div class="notice"><strong>Atenção à pessoa idosa.</strong> Objetivos devem priorizar preservação de massa, força, equilíbrio, mobilidade e autonomia, sem usar isoladamente IMC ou peso de referência do fabricante.</div>@endif
        @endif
        <table class="grid"><tr>
            <td class="box"><div class="label">Idade corporal</div><div class="metric-small">{{ $assessment['body_age'] ?? '-' }} anos</div><div class="muted">{{ $isRelaxmedic ? 'Estimativa proprietária; não representa idade biológica real.' : 'Indicador estimado pelo equipamento.' }}</div></td>
            <td class="box"><div class="label">Metabolismo basal</div><div class="metric-small">{{ $assessment['resting_metabolism_kcal'] ? number_format($assessment['resting_metabolism_kcal'], 0, ',', '.') : '-' }} kcal/dia</div><div class="muted">Não corresponde ao gasto energético total nem à recomendação de consumo.</div></td>
            <td class="box"><div class="label">Equipamento</div><div class="metric-small">{{ $manufacturer }}</div><div class="muted">@if($hasSpecificDeviceModel)Modelo {{ $deviceModel }} · @endif Referência {{ $assessment['reference_version'] ?? ($reference['classification_version'] ?? '1.0.0') }}</div></td>
        </tr></table>
    </div>
</section>

<section class="report-section">
    @include('bioimpedance.partials.report-header', ['pageTitle' => 'Análise e evolução', 'pageNumber' => 2])
    <div class="content">
        <div class="section-title" style="color: {{ $secondaryColor }};">Composição corporal detalhada</div>
        <table class="grid">
            <tr><td class="box" style="width: 50%;">@include('bioimpedance.partials.pdf-scale', ['title' => 'Gordura corporal', 'value' => $br($assessment['body_fat_percentage']).' %', 'indicator' => $bodyFat])</td><td class="box">@include('bioimpedance.partials.pdf-scale', ['title' => 'Músculo esquelético', 'value' => $br($assessment['skeletal_muscle_percentage']).' %', 'indicator' => $muscle])</td></tr>
            <tr><td class="box">@include('bioimpedance.partials.pdf-scale', ['title' => 'Gordura visceral', 'value' => $br($assessment['visceral_fat_level'], $isRelaxmedic ? 1 : 0).' nível', 'indicator' => $visceral])<div class="muted" style="margin-top: 5px;">Índice do dispositivo; não representa quantidade em kg.</div></td><td class="box"><div class="label">IMC calculado</div><div class="metric-value">{{ $br($assessment['calculated_bmi']) }}</div><span class="badge {{ $tone($bmi) }}">{{ $displayClassification($bmi) }}</span><div class="muted" style="margin-top: 5px;">Origem: cálculo do sistema com peso e altura. Deve ser interpretado com composição corporal, histórico e contexto individual.</div></td></tr>
        </table>
        @if($isRelaxmedic && count($relaxmedicMetrics))
            <div class="section-title" style="color: {{ $secondaryColor }};">Indicadores Relaxmedic</div>
            <table class="grid">
                @foreach(array_chunk($relaxmedicMetrics, 3, true) as $row)
                    <tr>@foreach($row as $label => $value)<td class="box" style="width: 33.33%;"><div class="label">{{ $label }}</div><div class="metric-small">{{ $value }}</div>@if(isset($metricNotes[$label]))<div class="muted" style="margin-top: 3px; font-size: 7px;">{{ $metricNotes[$label] }}</div>@else<div class="muted" style="margin-top: 3px; font-size: 7px;">Estimativa do equipamento.</div>@endif</td>@endforeach @for($i = count($row); $i < 3; $i++)<td></td>@endfor</tr>
                @endforeach
            </table>
            <div class="notice"><strong>Por que as classificações podem ser diferentes?</strong> O IMC é calculado pelo sistema usando peso e altura. “Nível de obesidade” e “Tipo de corpo” são classificações distintas importadas do RelaxFit; o tipo corporal é proprietário do aplicativo e não equivale à classificação do IMC.</div>
        @endif
        <div class="section-title" style="color: {{ $secondaryColor }};">Evolução</div>
        @if(($comparison['history_count'] ?? 0) >= 2 && count($comparison['trends'] ?? []))
            <p class="muted">Até seis avaliações não canceladas, realizadas no mesmo modelo de equipamento.</p>
            <table class="trend-grid"><tr>
                @foreach($comparison['trends'] as $trend)
                    @php
                        $firstTrendPoint = $trend['points'][0];
                        $lastTrendPoint = $trend['points'][count($trend['points']) - 1];
                        $previousTrendPoint = $trend['points'][count($trend['points']) - 2] ?? null;
                        $trendDelta = $previousTrendPoint
                            ? round($lastTrendPoint['value'] - $previousTrendPoint['value'], $trend['decimals'])
                            : null;
                    @endphp
                    <td class="trend-card" style="width: 33.33%;">
                        <div class="label">{{ $trend['label'] }}</div>
                        <div class="trend-current">{{ $br($lastTrendPoint['value'], $trend['decimals']) }} {{ $trend['unit'] }}</div>
                        @if($trend['has_evolution'])
                            <div class="trend-comparison">Anterior: {{ $br($previousTrendPoint['value'], $trend['decimals']) }} {{ $trend['unit'] }} → <strong>Atual: {{ $br($lastTrendPoint['value'], $trend['decimals']) }} {{ $trend['unit'] }}</strong></div>
                            <svg class="trend-chart" viewBox="0 0 300 84" preserveAspectRatio="none" aria-label="Evolução de {{ $trend['label'] }}">
                                <line x1="10" y1="72" x2="290" y2="72" stroke="#d0d5dd" stroke-width="1" />
                                <polyline points="{{ $trend['polyline'] }}" fill="none" stroke="#667085" stroke-width="2.5" />
                                @foreach($trend['points'] as $point)<circle cx="{{ $point['x'] }}" cy="{{ $point['y'] }}" r="3.5" fill="{{ $primaryColor }}" />@endforeach
                            </svg>
                            <table class="trend-dates"><tr><td>{{ $firstTrendPoint['date'] }}</td><td>{{ $lastTrendPoint['date'] }}</td></tr></table>
                            <div class="muted" style="margin-top: 2px;">Desde a avaliação anterior: {{ $trendDelta > 0 ? '+' : '' }}{{ $br($trendDelta, $trend['decimals']) }} {{ $trend['unit'] }}</div>
                        @else
                            <div class="muted" style="margin-top: 8px;">Linha de base: ainda sem segundo valor comparável.</div>
                        @endif
                    </td>
                @endforeach
            </tr></table>
            <p class="muted">As linhas mostram apenas tendências numéricas e não indicam melhora ou piora isoladamente.</p>
        @elseif($assessment['is_canceled'])
            <div class="summary muted">Avaliações canceladas não participam de comparações ou gráficos evolutivos.</div>
        @else
            <div class="summary muted"><strong>Linha de base.</strong> Esta é a primeira avaliação não cancelada neste modelo de equipamento. Os gráficos serão exibidos quando houver uma nova medição comparável.</div>
        @endif
    </div>
</section>

<section class="report-section page-guidance">
    @include('bioimpedance.partials.report-header', ['pageTitle' => 'Orientações e validação', 'pageNumber' => 3])
    <div class="content">
        <div class="section-title" style="color: {{ $secondaryColor }};">Interpretação profissional</div>
        <div class="summary">@if($assessment['notes'])<p style="white-space: pre-line;">{{ $assessment['notes'] }}</p>@else<p class="muted">Nenhuma observação profissional foi registrada.</p>@endif<div class="origin-note">{{ $observationOriginLabels[$assessment['observation_origin'] ?? 'professional'] }}</div></div>
        <div class="section-title" style="color: {{ $secondaryColor }};">Orientações aprovadas</div>
        @if($assessment['care_guidance'] ?? null)
            @foreach($guidanceSections as $section)
                <div class="guidance-section"><div class="guidance-section-title">{{ $section['title'] }}</div><div>{{ $section['text'] }}</div></div>
            @endforeach
            <p class="muted" style="margin: 5px 2px 0;">Conteúdo educativo revisado pelo profissional; não substitui avaliação médica, nutricional ou de educação física.</p>
            @if(count($assessment['care_guidance_sources'] ?? []))<div class="summary"><div class="label">Referências utilizadas</div><ul class="source-list">@foreach($assessment['care_guidance_sources'] as $source)<li>{{ $source['document'] ?? 'Documento de referência' }}@if($source['version'] ?? null) · versão {{ $source['version'] }}@endif · {{ $source['section'] ?? 'Seção não informada' }}</li>@endforeach</ul></div>@endif
        @else
            <div class="summary muted">Nenhuma orientação educativa foi aprovada para esta avaliação.</div>
        @endif
        <div class="section-title" style="color: {{ $secondaryColor }};">Validação e rastreabilidade</div>
        <table class="validation">
            <tr><td style="width: 50%;"><div class="label">Profissional responsável</div><strong>{{ $assessment['professional_name'] ?? 'Não informado' }}</strong></td><td><div class="label">Data da avaliação</div><strong>{{ \Illuminate\Support\Carbon::parse($assessment['evaluated_at'])->format('d/m/Y H:i') }}</strong></td></tr>
            <tr><td><div class="label">Orientação aprovada por</div><strong>{{ $assessment['care_guidance_approved_by_name'] ?? 'Não aplicável' }}</strong></td><td><div class="label">Aprovação</div><strong>{{ ($assessment['care_guidance_approved_at'] ?? null) ? \Illuminate\Support\Carbon::parse($assessment['care_guidance_approved_at'])->format('d/m/Y H:i') : 'Não aplicável' }}</strong></td></tr>
            <tr><td><div class="label">Origem dos dados</div><strong>{{ $isRelaxmedic ? 'Imagem processada com conferência humana' : 'Entrada manual pelo profissional' }}</strong></td><td><div class="label">Correções registradas</div><strong>{{ $assessment['correction_count'] ?? 0 }}</strong></td></tr>
        </table>
        @if($isRelaxmedic)<div class="notice"><strong>Origem auditável preservada.</strong> Os identificadores técnicos da imagem permanecem no registro interno de auditoria e não são exibidos no relatório entregue ao cliente. A imagem original não foi armazenada.</div>@endif
        <div class="keep-together">
            <div class="section-title" style="color: {{ $secondaryColor }};">Limites do relatório</div>
            <div class="summary"><p>{{ $clinic['technical_notice'] }}</p></div>
        </div>
    </div>
</section>
</body>
</html>
