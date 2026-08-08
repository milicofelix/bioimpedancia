<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Relatório de Bioimpedância</title>
    <style>
        @page { margin: 18px; }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            color: #172033;
            font-family: DejaVu Sans, Arial, sans-serif;
            font-size: 10px;
            line-height: 1.35;
            background: #ffffff;
        }
        .page {
            border: 1px solid #f2c7cf;
            border-radius: 10px;
            overflow: hidden;
        }
        .header {
            padding: 16px 20px 14px;
            background: #fbf2f4;
            border-bottom: 1px solid #f0c8cf;
        }
        .brand, .report-title { display: inline-block; vertical-align: middle; width: 49%; }
        .logo { width: 230px; max-height: 84px; object-fit: contain; }
        .report-title { text-align: right; }
        .report-title h1 {
            margin: 0 0 8px;
            color: #4a4a4a;
            font-size: 18px;
            text-transform: uppercase;
        }
        .muted { color: #667085; }
        .body { padding: 16px 20px 14px; background: #fffafa; }
        .client-grid, .hero-grid, .composition-grid, .protocol-grid { width: 100%; border-collapse: separate; border-spacing: 8px; }
        .box {
            background: #ffffff;
            border: 1px solid #f2d6dc;
            border-radius: 8px;
            padding: 10px;
        }
        .label {
            color: #a55e6d;
            font-size: 8px;
            font-weight: 700;
            text-transform: uppercase;
        }
        .client-name { margin: 4px 0 0; font-size: 17px; font-weight: 700; }
        .metric-value { margin-top: 6px; color: #101828; font-size: 26px; font-weight: 700; line-height: 1; }
        .metric-unit { color: #667085; font-size: 11px; }
        .badge {
            display: inline-block;
            margin-top: 8px;
            padding: 5px 9px;
            border-radius: 999px;
            font-size: 9px;
            font-weight: 700;
            text-transform: uppercase;
        }
        .good { background: #dff7ea; color: #087443; }
        .warning { background: #fff1c2; color: #9a6700; }
        .danger { background: #ffe4e8; color: #b42318; }
        .attention { background: #e0f2fe; color: #075985; }
        .neutral, .pending { background: #f2f4f7; color: #475467; }
        .section-title {
            margin: 10px 0 4px;
            color: #4a4a4a;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
        }
        .scale {
            width: 100%;
            height: 8px;
            margin-top: 8px;
            border-radius: 3px;
            overflow: hidden;
            background: #e5e7eb;
        }
        .segment { height: 8px; float: left; }
        .blue { background: #3b82f6; }
        .emerald { background: #10b981; }
        .amber { background: #f59e0b; }
        .rose { background: #f43f5e; }
        .slate { background: #cbd5e1; }
        .pointer-row { position: relative; height: 10px; }
        .pointer {
            position: absolute;
            top: 0;
            width: 0;
            height: 0;
            margin-left: -5px;
            border-left: 5px solid transparent;
            border-right: 5px solid transparent;
            border-top: 8px solid #111827;
        }
        .legend { width: 100%; border-collapse: collapse; margin-top: 2px; }
        .legend td { color: #667085; font-size: 7px; text-align: center; }
        .summary {
            margin-top: 10px;
            padding: 10px;
            border: 1px solid #f2d6dc;
            border-radius: 8px;
            background: #ffffff;
        }
        .footer {
            padding: 10px 20px 12px;
            border-top: 1px solid #e5e7eb;
            color: #667085;
            font-size: 8px;
        }
        .canceled {
            margin-bottom: 10px;
            padding: 9px;
            border: 1px solid #fecdd3;
            border-radius: 8px;
            background: #fff1f2;
            color: #9f1239;
        }
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
@endphp
<div class="page">
    <div class="header">
        <div class="brand">
            @if($clinic['logo_data_uri'])
                <img class="logo" src="{{ $clinic['logo_data_uri'] }}" alt="{{ $clinic['display_name'] }}">
            @else
                <strong>{{ $clinic['display_name'] }}</strong>
            @endif
        </div>
        <div class="report-title">
            <h1>Relatório de bioimpedância</h1>
            <div>Avaliação Nº {{ str_pad((string) $assessment['id'], 4, '0', STR_PAD_LEFT) }}</div>
            <div class="muted">{{ \Illuminate\Support\Carbon::parse($assessment['evaluated_at'])->format('d/m/Y H:i') }}</div>
            <div class="muted">Emitido em {{ $issuedAt?->format('d/m/Y H:i') }}</div>
        </div>
    </div>
    <div class="body">
        @if($assessment['is_canceled'])
            <div class="canceled"><strong>Avaliação cancelada.</strong> {{ $assessment['cancellation_reason'] }}</div>
        @endif

        <table class="client-grid">
            <tr>
                <td class="box" style="width: 46%;">
                    <div class="label">Cliente</div>
                    <div class="client-name">{{ $client['full_name'] }}</div>
                </td>
                <td class="box"><div class="label">Idade</div><strong>{{ $assessmentAge }} anos</strong></td>
                <td class="box"><div class="label">Sexo</div><strong>{{ $sexLabels[$assessmentSex] ?? '-' }}</strong></td>
                <td class="box"><div class="label">Altura</div><strong>{{ $br($assessmentHeight, 0) }} cm</strong></td>
            </tr>
        </table>

        <table class="hero-grid">
            <tr>
                <td class="box" style="background: #f8e8eb;">
                    <div class="label">Peso</div>
                    <div class="metric-value">{{ $br($assessment['weight_kg']) }} <span class="metric-unit">kg</span></div>
                </td>
                <td class="box" style="background: #f8e8eb;">
                    <div class="label">IMC calculado</div>
                    <div class="metric-value">{{ $br($assessment['calculated_bmi']) }}</div>
                    <span class="badge {{ $tone($bmi) }}">{{ $bmi['classification'] ?? '-' }}</span>
                </td>
                <td class="box" style="background: #f8e8eb;">
                    <div class="label">Idade corporal</div>
                    <div class="metric-value">{{ $assessment['body_age'] ?? '-' }} <span class="metric-unit">anos</span></div>
                    @if($assessment['body_age'])
                        <div class="muted">{{ abs($assessment['body_age'] - $assessmentAge) }} anos {{ $assessment['body_age'] > $assessmentAge ? 'acima' : 'abaixo' }} da idade cronológica</div>
                    @endif
                </td>
            </tr>
        </table>

        <div class="section-title">Composição corporal</div>
        <table class="composition-grid">
            <tr>
                <td class="box" style="width: 50%;">@include('bioimpedance.partials.pdf-scale', ['title' => 'Gordura corporal', 'value' => $br($assessment['body_fat_percentage']).' %', 'indicator' => $bodyFat])</td>
                <td class="box" style="width: 50%;">@include('bioimpedance.partials.pdf-scale', ['title' => 'Músculo esquelético', 'value' => $br($assessment['skeletal_muscle_percentage']).' %', 'indicator' => $muscle])</td>
            </tr>
            <tr>
                <td class="box">@include('bioimpedance.partials.pdf-scale', ['title' => 'Gordura visceral', 'value' => $br($assessment['visceral_fat_level'], 0).' nível', 'indicator' => $visceral])</td>
                <td class="box">
                    <div class="label">Metabolismo basal</div>
                    <div class="metric-value">{{ $assessment['resting_metabolism_kcal'] ? number_format($assessment['resting_metabolism_kcal'], 0, ',', '.') : '-' }} <span class="metric-unit">kcal/dia</span></div>
                    <span class="badge neutral">Estimativa diária</span>
                </td>
            </tr>
        </table>

        <div class="summary">
            <div class="section-title" style="margin-top: 0;">Síntese da avaliação</div>
            <p>{{ $assessment['analysis']['summary'] ?? 'Síntese indisponível.' }}</p>
            @if($assessment['notes'])
                <p><strong>Observações:</strong> {{ $assessment['notes'] }}</p>
            @endif
            <p class="muted">Equipamento {{ $reference['manufacturer'] ?? 'Omron' }} {{ $assessment['device_model'] ?? ($reference['model'] ?? 'HBF-514C') }} | Versão {{ $assessment['reference_version'] ?? ($reference['classification_version'] ?? '1.0.0') }} | Entrada manual</p>
        </div>
    </div>
    <div class="footer">
        <strong>Observações importantes.</strong> Os resultados de bioimpedância são estimativas e podem variar conforme hidratação, alimentação, ciclo hormonal, medicamentos e condições de medição. Este documento não substitui avaliação médica ou nutricional.
        <br>
        {{ $clinic['display_name'] }} - {{ $clinic['contact'] }} - Relatório nº {{ str_pad((string) $assessment['id'], 4, '0', STR_PAD_LEFT) }}
    </div>
</div>
</body>
</html>
