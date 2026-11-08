<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Relatório de Bioimpedância</title>
    <style>
        @page { margin: 76px 18px 42px; }
        * { box-sizing: border-box; }
        body { margin: 0; color: #172033; font-family: DejaVu Sans, Arial, sans-serif; font-size: 9px; line-height: 1.4; }
        .document-header { position: fixed; top: -63px; left: 0; right: 0; height: 52px; padding: 6px 12px; border-bottom: 1px solid #cde6ea; background: #fff; }
        .document-brand, .document-identification { display: inline-block; width: 49%; vertical-align: middle; }
        .document-logo { display: inline-block; width: 28px; height: 40px; object-fit: contain; vertical-align: middle; }
        .document-brand-copy { display: inline-block; margin-left: 6px; vertical-align: middle; }
        .document-brand-name { margin: 0; color: #052f59; font-size: 16px; font-weight: 700; line-height: 1; letter-spacing: -1px; }
        .document-brand-name span { color: #052f59; }
        .document-brand-name em { color: #159fbd; font-style: normal; }
        .document-brand-subtitle { margin: 3px 0 0; color: #526172; font-size: 5px; font-weight: 700; letter-spacing: 1.2px; line-height: 1; text-transform: uppercase; }
        .document-identification { color: #667085; font-size: 7px; text-align: right; }
        .document-identification strong { color: #172033; font-size: 9px; }
        .document-footer { position: fixed; bottom: -31px; left: 0; right: 0; height: 24px; padding: 6px 12px; border-top: 1px solid #cde6ea; color: #667085; font-size: 7px; background: #fff; }
        .footer-right { float: right; }
        .report-section { page-break-before: always; }
        .report-section-first { page-break-before: auto; }
        .section-header { padding: 10px 18px 9px; border: 1px solid #b2e5e6; border-bottom: 2px solid #087f98; border-radius: 8px 8px 0 0; background: #eef8fa; page-break-after: avoid; }
        .section-heading h1 { margin: 2px 0 3px; font-size: 17px; }
        .eyebrow, .label, .section-title { font-size: 7px; font-weight: 700; letter-spacing: .5px; text-transform: uppercase; }
        .content { padding: 12px 18px; border-right: 1px solid #b2e5e6; border-bottom: 1px solid #b2e5e6; border-left: 1px solid #b2e5e6; background: #f8fcfd; }
        .section-title { margin: 12px 0 6px; font-size: 10px; letter-spacing: .7px; page-break-after: avoid; }
        .grid { width: 100%; border-collapse: separate; border-spacing: 6px; }
        .grid tr { page-break-inside: avoid; }
        .box { padding: 9px; border: 1px solid #cde6ea; border-radius: 8px; background: #fff; vertical-align: top; page-break-inside: avoid; }
        .hero { background: #e2f4f7; }
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
        .summary { border: 1px solid #cde6ea; background: #fff; }
        .notice { border-left: 3px solid #087f98; background: #eef8fa; }
        .guidance { border: 1px solid #b7e3dc; background: #f1fbf8; }
        .guidance-section { margin-top: 5px; padding: 7px 10px; border: 1px solid #b7e3dc; border-radius: 7px; background: #f7fcfa; page-break-inside: auto; }
        .guidance-section-title { margin-bottom: 3px; color: #176b5b; font-size: 8px; font-weight: 700; page-break-after: avoid; }
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
        .composition-page .content { padding-top: 8px; padding-bottom: 8px; }
        .composition-page .box { padding: 7px; }
        .composition-page .section-title { margin-top: 9px; margin-bottom: 5px; }
        .composition-page .notice { margin-top: 6px; padding: 7px 10px; }
        .composition-page .trend-chart { height: 56px; }
        .comparison-page .segmental-comparison th, .comparison-page .segmental-comparison td { padding: 4px 6px; }
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
        .report-closing { page-break-inside: avoid; }
        .report-closing .report-limits { font-size: 8px; line-height: 1.35; }
        .report-limits p { margin: 3px 0 0; }
        .segmental-page .content { padding: 8px 12px; }
        .segmental-page .notice { margin-top: 4px; padding: 6px 8px; }
        .segmental-grid { width: 100%; border-collapse: separate; border-spacing: 5px; table-layout: fixed; page-break-inside: avoid; }
        .segmental-grid > tbody > tr > td { width: 50%; padding: 5px; border: 1px solid #cde6ea; border-radius: 8px; background: #fff; vertical-align: top; }
        .segmental-grid, .segmental-grid tr, .segmental-grid td, .segmental-map, .segmental-visual, .segmental-table { page-break-inside: avoid; }
        .segmental-title { color: #052f59; font-size: 10px; font-weight: 700; text-align: center; }
        .segmental-subtitle { margin-top: 2px; color: #667085; font-size: 6px; text-align: center; }
        .segmental-visual { width: 100%; height: 190px; border-collapse: collapse; table-layout: fixed; }
        .segmental-visual td { padding: 2px; border: 0; vertical-align: middle; }
        .segmental-visual .body-column { width: 42%; text-align: center; }
        .segmental-silhouette { display: block; width: 100%; height: 178px; object-fit: contain; }
        .segmental-value { color: #334155; font-size: 6px; line-height: 1.35; }
        .segmental-value strong { color: #172033; font-size: 7px; }
        .segmental-value-right { text-align: right; }
        .segmental-table { width: 100%; border-collapse: collapse; font-size: 6px; }
        .segmental-table caption { padding: 4px 0 3px; color: #475467; font-size: 6px; font-weight: 700; text-align: left; text-transform: uppercase; }
        .segmental-table th, .segmental-table td { padding: 2px 3px; border: 1px solid #e5e7eb; text-align: left; }
        .segmental-table thead th { background: #eef8fa; color: #475467; text-transform: uppercase; }
        .segmental-comparison { width: 100%; border-collapse: collapse; margin-top: 8px; font-size: 7px; }
        .segmental-comparison thead { display: table-header-group; }
        .segmental-comparison th, .segmental-comparison td { padding: 6px; border: 1px solid #e5e7eb; text-align: left; }
        .segmental-comparison th { background: #eef8fa; color: #475467; text-transform: uppercase; }
        .segmental-comparison tr { page-break-inside: avoid; }
        .segmental-comparison caption { padding: 0 0 5px; color: #475467; font-size: 7px; text-align: left; }
        .segmental-legend { width: 100%; border-collapse: separate; border-spacing: 4px; margin-top: 3px; }
        .segmental-legend td { padding: 3px 5px; border: 1px solid #e5e7eb; border-radius: 6px; background: #fff; font-size: 6px; text-align: center; }
        .technical-appendix { margin-top: 7px; padding-top: 3px; border-top: 1px solid #cde6ea; }
        .technical-appendix .notice, .technical-appendix .summary { padding: 6px 8px; }
        .technical-appendix .section-title { margin-top: 5px; }
        .impedance-table { page-break-inside: auto; }
        .impedance-table thead { display: table-header-group; }
        .impedance-table tr { page-break-inside: avoid; }
        .impedance-table th, .impedance-table td { padding: 4px 5px; }
        .weight-control-title { margin-top: 6px; margin-bottom: 3px; }
        .complementary-references-page .weight-control-title { margin-top: 0; }
        .weight-control-table { margin-top: 3px; page-break-inside: auto; font-size: 6.5px; }
        .weight-control-table thead { display: table-header-group; }
        .weight-control-table tr { page-break-inside: avoid; }
        .weight-control-table th, .weight-control-table td { padding: 3px 5px; }
        .weight-control-notice { margin-top: 4px; padding: 6px 8px; page-break-inside: auto; }
        .segmental-page .segmental-visual { height: 238px; }
        .segmental-page .segmental-silhouette { height: 224px; }
        .segmental-page .segmental-value { font-size: 7.5px; line-height: 1.4; }
        .segmental-page .segmental-value strong { font-size: 8.5px; }
        .segmental-page .segmental-table { font-size: 7px; }
        .segmental-page .segmental-table th, .segmental-page .segmental-table td { padding: 3px 4px; }
        .segmental-summary { margin: 4px 0 6px; padding: 6px 8px; border: 1px solid #e5e7eb; border-radius: 7px; background: #fff; font-size: 7px; }
        .review-status { color: #176b5b; font-weight: 700; }
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
    $primaryColor = $clinic['primary_color'] ?? '#087f98';
    $secondaryColor = $clinic['secondary_color'] ?? '#052f59';
    $isBodyscan = ($assessment['device_model'] ?? null) === 'Bodyscan Pro';
    $isRelaxmedic = in_array(($assessment['device_model'] ?? null), ['Relaxmedic', 'Bodyscan Pro'], true);
    $extendedMetrics = collect($assessment['extended_metrics'] ?? []);
    $extendedMetricsByCode = $extendedMetrics->keyBy('metric_code');
    $impedanceReadings = collect($assessment['impedance_readings'] ?? []);
    $impedanceByKey = $impedanceReadings->keyBy(fn (array $reading) => ($reading['frequency_khz'] ?? '').'|'.($reading['region'] ?? '').'|'.($reading['side'] ?? ''));
    $isFitdays = ($assessment['measurement_application'] ?? null) === 'fitdays';
    $fitdaysControlCodes = ['recommended_target_weight', 'weight_control', 'fat_control', 'muscle_control'];
    $fitdaysControlMetrics = $extendedMetrics->whereIn('metric_code', $fitdaysControlCodes)->values();
    $fitdaysReferenceMetrics = $extendedMetrics
        ->reject(fn (array $metric) => in_array($metric['metric_code'] ?? null, $fitdaysControlCodes, true))
        ->filter(fn (array $metric) => ($metric['source_application'] ?? null) === 'fitdays'
            && (($metric['application_classification'] ?? null) !== null
                || ($metric['printed_minimum'] ?? null) !== null
                || ($metric['printed_maximum'] ?? null) !== null
                || ($metric['weight_proportion_percentage'] ?? null) !== null
                || in_array($metric['metric_code'] ?? null, ['waist_hip_ratio', 'asmi', 'body_score', 'proprietary_obesity_index', 'body_balance_assessment'], true)))
        ->values();
    $hasFitdaysDataPage = $isFitdays && ($fitdaysReferenceMetrics->isNotEmpty() || $fitdaysControlMetrics->isNotEmpty());
    $hasImpedanceData = $isFitdays && $impedanceReadings->isNotEmpty();
    $segmentalMeasurements = $assessment['segmental_measurements'] ?? [];
    $segmentalComparison = $comparison['segmental'] ?? null;
    $comparability = $comparison['comparability'] ?? null;
    $hasSegmentalComparison = (bool) ($segmentalComparison['has_previous'] ?? false);
    $hasSegmentalPage = $isBodyscan && count($segmentalMeasurements) > 0;
    $hasDetailedComparison = count($comparison['metrics'] ?? []) > 0
        || (!($comparison['impedance']['is_baseline'] ?? true) && count($comparison['impedance']['readings'] ?? []) > 0);
    $totalSections = 3
        + ($hasFitdaysDataPage ? 1 : 0)
        + ($hasDetailedComparison ? 1 : 0)
        + ($hasSegmentalPage ? 1 : 0)
        + ($hasSegmentalComparison ? 1 : 0);
    $manufacturer = trim((string) ($reference['manufacturer'] ?? ($isRelaxmedic ? 'Relaxmedic' : 'Omron')));
    $deviceModel = trim((string) ($reference['model'] ?? $assessment['device_model'] ?? ''));
    $genericDeviceModels = ['relaxmedic', 'relaxmedic/relaxfit'];
    $hasSpecificDeviceModel = $deviceModel !== ''
        && mb_strtolower($deviceModel) !== mb_strtolower($manufacturer)
        && ! in_array(mb_strtolower($deviceModel), $genericDeviceModels, true);
    $athleteModeLabel = match ($assessment['athlete_mode_at_assessment'] ?? null) {
        true => 'Ativado',
        false => 'Desativado',
        default => 'Não informado',
    };
    $extendedMetricDisplayValue = function (?array $metric, int $decimals = 2) use ($br): string {
        if (! $metric) {
            return '-';
        }
        if (($metric['numeric_value'] ?? null) !== null) {
            return trim($br($metric['numeric_value'], $decimals).' '.($metric['unit'] ?? ''));
        }

        return (string) ($metric['text_value'] ?? $metric['raw_extracted_value'] ?? '-');
    };
    $displayClassification = fn ($indicator, $fallback = '-') => $indicator['classification'] ?? $fallback;
    $bodyFatDeviceMetric = $extendedMetricsByCode->get('body_fat_percentage');
    $deviceTone = function (?string $classification): string {
        $normalized = mb_strtolower(trim((string) $classification));
        return match (true) {
            str_contains($normalized, 'baixo') || str_contains($normalized, 'fino') => 'attention',
            str_contains($normalized, 'alto') => 'danger',
            str_contains($normalized, 'padrão') || str_contains($normalized, 'padrao') || str_contains($normalized, 'normal') || str_contains($normalized, 'excelente') => 'good',
            default => 'neutral',
        };
    };
    $bodyFatReportIndicator = $bodyFat;
    if (filled($bodyFatDeviceMetric['application_classification'] ?? null)) {
        $bodyFatReportIndicator = [
            ...$bodyFat,
            'classification' => $bodyFatDeviceMetric['application_classification'],
            'tone' => $deviceTone($bodyFatDeviceMetric['application_classification']),
            'pending' => false,
        ];
    }
    $executiveSummary = str_replace(
        'a referência de classificação Relaxmedic não está cadastrada',
        'o equipamento não forneceu uma classificação textual para este indicador',
        $assessment['analysis']['summary'] ?? 'Síntese indisponível.',
    );
    if (filled($bodyFatDeviceMetric['application_classification'] ?? null)) {
        $executiveSummary = preg_replace(
            '/Gordura corporal estimada pelo equipamento em ([^;]+); sem classificação disponível para este indicador\./u',
            'Gordura corporal estimada pelo equipamento em $1, com classificação "'.$bodyFatDeviceMetric['application_classification'].'" apresentada pelo equipamento.',
            $executiveSummary,
        ) ?? $executiveSummary;
    }
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
            $paragraph = str_replace(
                ['Se estiver apto,', 'Se estiver apta,'],
                'Conforme suas condições de saúde,',
                $paragraph,
            );
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
    $compositionDetailMetrics = $isFitdays
        ? array_intersect_key($relaxmedicMetrics, array_flip([
            'Massa corporal magra',
            'Gordura subcutânea',
            'Água corporal',
            'Massa muscular',
            'Massa óssea',
            'Proteína',
        ]))
        : $relaxmedicMetrics;
    $metricNotes = [
        'Massa corporal magra' => 'Estimativa do equipamento; não comprova força ou função.',
        'Massa muscular' => 'Estimativa; não substitui avaliação funcional.',
        'Massa óssea' => 'Não corresponde a densitometria óssea.',
        'Proteína' => 'Não identifica ingestão ou deficiência nutricional.',
        'Massa de proteína' => 'Não identifica deficiência nutricional.',
        'Peso corporal ideal' => 'Referência do fabricante, não uma meta prescrita.',
        'Nível de obesidade' => 'Classificação apresentada pelo equipamento; não equivale ao IMC.',
        'Tipo de corpo' => 'Classificação proprietária do equipamento; não equivale ao IMC.',
    ];
    $fatPercentageMetric = $extendedMetricsByCode->get('body_fat_percentage');
    $fatMassMetric = $extendedMetricsByCode->get('fat_mass');
    $hasAmbiguousBodyFatRange = $fatPercentageMetric && $fatMassMetric
        && ($fatPercentageMetric['printed_minimum'] ?? null) !== null
        && ($fatPercentageMetric['printed_maximum'] ?? null) !== null
        && abs((float) $fatPercentageMetric['printed_minimum'] - (float) ($fatMassMetric['printed_minimum'] ?? -9999)) < 0.001
        && abs((float) $fatPercentageMetric['printed_maximum'] - (float) ($fatMassMetric['printed_maximum'] ?? -9999)) < 0.001;
    $segmentStateLabel = fn (string $classification): string => match ($classification) {
        'low' => 'abaixo da referência',
        'standard' => 'dentro da referência',
        'high' => 'acima da referência',
        default => 'não identificada',
    };
    $segmentSummary = function (string $type, string $label) use ($segmentalMeasurements, $segmentStateLabel): string {
        $items = collect($segmentalMeasurements)->where('type', $type);
        $regions = ['arm|left' => 'braço esquerdo', 'arm|right' => 'braço direito', 'trunk|center' => 'tronco', 'leg|left' => 'perna esquerda', 'leg|right' => 'perna direita'];
        $groups = $items->groupBy(fn (array $item) => $item['classification'] ?? 'unknown');
        $parts = $groups->map(function ($group, string $classification) use ($regions, $segmentStateLabel): string {
            $names = $group->map(fn (array $item) => $regions[($item['region'] ?? '').'|'.($item['side'] ?? '')] ?? 'região')->values()->all();
            return implode(', ', $names).': '.$segmentStateLabel($classification);
        })->values()->all();

        return $label.': '.implode('; ', $parts).'.';
    };
    $hasReviewedObservation = in_array($assessment['observation_origin'] ?? 'none', ['professional', 'ai_edited_and_approved', 'ai_approved'], true);
    $hasReviewedGuidance = filled($assessment['care_guidance_approved_at'] ?? null);
    $reviewedByName = $assessment['care_guidance_approved_by_name'] ?? $assessment['observation_reviewed_by_name'] ?? null;
    $reviewedAt = $assessment['care_guidance_approved_at'] ?? $assessment['observation_reviewed_at'] ?? null;
    $hasRecordedProfessionalReview = ($hasReviewedObservation || $hasReviewedGuidance) && filled($reviewedByName) && filled($reviewedAt);
    $responsibleProfessionalName = $reviewedByName ?: ($assessment['professional_name'] ?? 'Não informado');
    $hasEvolution = ($comparison['history_count'] ?? 0) >= 2 && count($comparison['trends'] ?? []);
    $bodyBalanceMetric = $extendedMetricsByCode->get('body_balance_assessment');
    $bodyBalanceText = trim((string) ($bodyBalanceMetric['text_value'] ?? $bodyBalanceMetric['raw_extracted_value'] ?? ''));
    $bodyBalanceItems = collect(['Superior', 'Mais baixo', 'Parte superior'])->map(function (string $label, int $index) use ($bodyBalanceText): ?array {
        $labels = ['Superior', 'Mais baixo', 'Parte superior'];
        $start = mb_stripos($bodyBalanceText, $label);
        if ($start === false) {
            return null;
        }
        $valueStart = $start + mb_strlen($label);
        $nextLabel = $labels[$index + 1] ?? null;
        $nextStart = $nextLabel ? mb_stripos($bodyBalanceText, $nextLabel, $valueStart) : false;
        $value = trim(mb_substr($bodyBalanceText, $valueStart, $nextStart === false ? null : $nextStart - $valueStart));
        return $value !== '' ? ['label' => $label, 'value' => $value] : null;
    })->filter()->values()->all();
    $segmentalFatTotal = collect($segmentalMeasurements)->where('type', 'fat')->sum(fn (array $item) => is_numeric($item['mass_kg'] ?? null) ? (float) $item['mass_kg'] : 0.0);
    $segmentalFatDifference = is_numeric($assessment['fat_mass_kg'] ?? null) && $segmentalFatTotal > 0
        ? abs($segmentalFatTotal - (float) $assessment['fat_mass_kg'])
        : null;
    $segmentalFatTolerance = is_numeric($assessment['fat_mass_kg'] ?? null) ? max(0.5, (float) $assessment['fat_mass_kg'] * 0.03) : null;
    $hasSegmentalFatDifference = $segmentalFatDifference !== null && $segmentalFatDifference > $segmentalFatTolerance;
@endphp

@include('bioimpedance.partials.report-document-header')
@include('bioimpedance.partials.report-footer')

<section class="report-section report-section-first">
    @include('bioimpedance.partials.report-header', ['pageTitle' => 'Visão executiva', 'pageNumber' => 1, 'totalSections' => $totalSections])
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
            <td class="box hero" style="width: 25%;"><div class="label">Gordura corporal</div><div class="metric-value">{{ $br($assessment['body_fat_percentage']) }} <span class="metric-unit">%</span></div>@if(!($bodyFatReportIndicator['pending'] ?? false))<span class="badge {{ $tone($bodyFatReportIndicator) }}">{{ $displayClassification($bodyFatReportIndicator, 'Estimativa') }}</span><div class="muted" style="margin-top: 3px;">Classificação do equipamento</div>@else<div class="muted" style="margin-top: 6px;">Estimativa do equipamento</div>@endif</td>
            <td class="box hero" style="width: 25%;"><div class="label">Músculo esquelético</div><div class="metric-value">{{ $br($assessment['skeletal_muscle_percentage']) }} <span class="metric-unit">%</span></div>@if(!($muscle['pending'] ?? false))<span class="badge {{ $tone($muscle) }}">{{ $displayClassification($muscle, 'Estimativa') }}</span>@else<div class="muted" style="margin-top: 6px;">Estimativa do equipamento</div>@endif</td>
        </tr></table>
        <div class="summary"><div class="section-title" style="margin-top: 0; color: {{ $primaryColor }};">Síntese da avaliação</div><p>{{ $executiveSummary }}</p></div>
        @if($isRelaxmedic)
            <div class="notice"><strong>Como interpretar este relatório.</strong> Os resultados da bioimpedância são estimativas e índices próprios do equipamento. A coerência matemática entre medidas não comprova precisão fisiológica e não substitui avaliação clínica ou funcional.</div>
            @if($assessmentAge >= 60)<div class="notice"><strong>Atenção à pessoa idosa.</strong> Objetivos devem priorizar preservação de massa, força, equilíbrio, mobilidade e autonomia, sem usar isoladamente IMC ou peso de referência do fabricante.</div>@endif
        @endif
        <table class="grid"><tr>
            <td class="box"><div class="label">Estimativa de idade corporal</div><div class="metric-small">{{ $assessment['body_age'] ?? '-' }} anos</div><div class="muted">Índice apresentado pelo equipamento; não é idade cronológica, biológica ou diagnóstico.</div></td>
            <td class="box"><div class="label">Metabolismo basal</div><div class="metric-small">{{ $assessment['resting_metabolism_kcal'] ? number_format($assessment['resting_metabolism_kcal'], 0, ',', '.') : '-' }} kcal/dia</div><div class="muted">Não corresponde ao gasto energético total nem à recomendação de consumo.</div></td>
            <td class="box">
                <div class="label">Equipamento</div>
                <div class="metric-small" style="font-size: 11px; white-space: nowrap;">{{ $manufacturer }}@if($assessment['device_model_code'] ?? null) · {{ $assessment['device_model_code'] }}@elseif($hasSpecificDeviceModel) · {{ $deviceModel }}@endif</div>
                <div class="muted">
                    @if($isBodyscan)Perfil da medição: {{ $athleteModeLabel === 'Ativado' ? 'Atleta' : ($athleteModeLabel === 'Desativado' ? 'Padrão' : 'Não informado') }}@else Referência {{ $assessment['reference_version'] ?? ($reference['classification_version'] ?? '1.0.0') }}@endif
                </div>
            </td>
        </tr></table>
    </div>
</section>

<section class="report-section composition-page">
    @include('bioimpedance.partials.report-header', ['pageTitle' => $hasEvolution ? 'Análise e evolução' : 'Composição corporal detalhada', 'pageNumber' => 2, 'totalSections' => $totalSections])
    <div class="content">
        <div class="section-title" style="color: {{ $secondaryColor }};">Composição corporal detalhada</div>
        <table class="grid">
            <tr><td class="box" style="width: 50%;">@include('bioimpedance.partials.pdf-scale', ['title' => 'Gordura corporal', 'value' => $br($assessment['body_fat_percentage']).' %', 'indicator' => $bodyFatReportIndicator])</td><td class="box">@include('bioimpedance.partials.pdf-scale', ['title' => 'Músculo esquelético', 'value' => $br($assessment['skeletal_muscle_percentage']).' %', 'indicator' => $muscle])</td></tr>
            <tr><td class="box">@include('bioimpedance.partials.pdf-scale', ['title' => 'Gordura visceral', 'value' => $br($assessment['visceral_fat_level'], $isRelaxmedic ? 1 : 0).' nível', 'indicator' => $visceral])<div class="muted" style="margin-top: 5px;">Índice do dispositivo; não representa quantidade em kg.</div></td><td class="box"><div class="label">IMC calculado</div><div class="metric-value">{{ $br($assessment['calculated_bmi']) }}</div><span class="badge {{ $tone($bmi) }}">{{ $displayClassification($bmi) }}</span><div class="muted" style="margin-top: 5px;">Calculado com peso e altura. Deve ser interpretado com composição corporal, histórico e contexto individual.</div></td></tr>
        </table>
        @if($isRelaxmedic && count($compositionDetailMetrics))
            <div class="section-title" style="color: {{ $secondaryColor }};">Indicadores adicionais</div>
            <table class="grid">
                @foreach(array_chunk($compositionDetailMetrics, 3, true) as $row)
                    <tr>@foreach($row as $label => $value)<td class="box" style="width: 33.33%;"><div class="label">{{ $label }}</div><div class="metric-small">{{ $value }}</div>@if(isset($metricNotes[$label]))<div class="muted" style="margin-top: 3px; font-size: 7px;">{{ $metricNotes[$label] }}</div>@else<div class="muted" style="margin-top: 3px; font-size: 7px;">Estimativa do equipamento.</div>@endif</td>@endforeach @for($i = count($row); $i < 3; $i++)<td></td>@endfor</tr>
                @endforeach
            </table>
            @if($isFitdays)
                <div class="notice"><strong>Classificações apresentadas.</strong> Rótulos como “alto”, “muito alto” ou “excelente” são referências do equipamento e não determinam automaticamente a interpretação profissional.</div>
            @else
                <div class="notice"><strong>Por que as classificações podem ser diferentes?</strong> O IMC é calculado usando peso e altura. “Nível de obesidade” e “Tipo de corpo” são referências distintas fornecidas pelo equipamento e não equivalem à classificação do IMC.</div>
            @endif
        @endif
        @if($hasEvolution)<div class="section-title" style="color: {{ $secondaryColor }};">Evolução</div>@endif
        @if($hasEvolution && $comparability)
            <div class="notice">
                <strong>Comparação descritiva.</strong> Somente valores tecnicamente compatíveis são apresentados. Quando o contexto de medição for diferente, os números devem ser comparados com cautela.
            </div>
        @endif
        @if($hasEvolution)
            <p class="muted">Até seis avaliações não canceladas com contexto técnico comparável. Somente indicadores presentes nas avaliações relacionadas são exibidos. As linhas mostram tendências numéricas e não indicam melhora ou piora isoladamente.</p>
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
        @elseif($assessment['is_canceled'])
            <div class="summary muted">Avaliações canceladas não participam de comparações ou gráficos evolutivos.</div>
        @endif
        @if($hasImpedanceData && ! $hasFitdaysDataPage)
            @include('bioimpedance.partials.report-fitdays-impedance', ['embedded' => true])
        @endif
    </div>
</section>

@if($hasFitdaysDataPage)
    @include('bioimpedance.partials.report-fitdays-data', ['pageNumber' => 3])
@endif

@if($hasDetailedComparison)
<section class="report-section comparison-page">
    @include('bioimpedance.partials.report-header', ['pageTitle' => 'Comparação compatível', 'pageNumber' => 3 + ($hasFitdaysDataPage ? 1 : 0), 'totalSections' => $totalSections])
    <div class="content">
        <div class="notice"><strong>{{ $comparability['label'] ?? 'Comparação descritiva' }}.</strong> Somente valores com tipo e unidade compatíveis são apresentados. As variações não representam automaticamente melhora ou piora clínica.</div>
        @if(count($comparison['metrics'] ?? []))
            <div class="section-title" style="color: {{ $secondaryColor }};">Métricas presentes nas duas avaliações</div>
            <table class="segmental-comparison">
                <thead><tr><th>Métrica comparável</th><th>Anterior</th><th>Atual</th><th>Variação numérica</th></tr></thead>
                <tbody>
                @foreach($comparison['metrics'] as $metric)
                    <tr><td><strong>{{ $metric['label'] }}</strong></td><td>{{ $br($metric['previous'], $metric['decimals'] ?? 1) }} {{ $metric['unit'] }}</td><td>{{ $br($metric['current'], $metric['decimals'] ?? 1) }} {{ $metric['unit'] }}</td><td>{{ $metric['delta'] > 0 ? '+' : '' }}{{ $br($metric['delta'], $metric['decimals'] ?? 1) }} {{ $metric['unit'] }}</td></tr>
                @endforeach
                </tbody>
            </table>
        @endif
        @if(!($comparison['impedance']['is_baseline'] ?? true) && count($comparison['impedance']['readings'] ?? []))
            <div class="section-title" style="color: {{ $secondaryColor }};">Impedância disponível nas duas avaliações</div>
            <table class="segmental-comparison">
                <caption>Leitura elétrica técnica, preservada para comparação e sem interpretação diagnóstica.</caption>
                <thead><tr><th>Frequência</th><th>Região</th><th>Anterior</th><th>Atual</th><th>Variação</th></tr></thead>
                <tbody>
                @foreach($comparison['impedance']['readings'] as $reading)
                    @if($reading['previous_ohms'] !== null && $reading['current_ohms'] !== null)
                        <tr><td>{{ $reading['frequency_khz'] }} kHz</td><td>{{ ucfirst($reading['region']) }} · {{ $reading['side'] }}</td><td>{{ $br($reading['previous_ohms'], 2) }} Ω</td><td>{{ $br($reading['current_ohms'], 2) }} Ω</td><td>{{ $reading['delta_ohms'] > 0 ? '+' : '' }}{{ $br($reading['delta_ohms'], 2) }} Ω</td></tr>
                    @endif
                @endforeach
                </tbody>
            </table>
        @endif
    </div>
</section>
@endif

@if($hasSegmentalPage)
<section class="report-section segmental-page">
    @include('bioimpedance.partials.report-header', ['pageTitle' => 'Análise segmentar', 'pageNumber' => 3 + ($hasFitdaysDataPage ? 1 : 0) + ($hasDetailedComparison ? 1 : 0), 'totalSections' => $totalSections])
    <div class="content">
        <div class="notice"><strong>Leitura segmentar.</strong> L representa o lado esquerdo e R o lado direito. Massa, percentual de referência e classificação também são apresentados nas tabelas textuais.</div>
        <div class="segmental-summary"><strong>Resumo descritivo.</strong> {{ $segmentSummary('fat', 'Gordura segmentar') }} {{ $segmentSummary('muscle', 'Equilíbrio muscular') }} As classificações reproduzem o equipamento e não representam diagnóstico.</div>
        @if($hasSegmentalFatDifference)<div class="notice"><strong>Conferência técnica.</strong> A soma arredondada da gordura segmentar ({{ $br($segmentalFatTotal) }} kg) difere da massa gorda total ({{ $br($assessment['fat_mass_kg']) }} kg) em {{ $br($segmentalFatDifference) }} kg. Os valores originais foram preservados e devem ser conferidos no relatório de origem.</div>@endif
        <table class="segmental-grid"><tr>
            <td>@include('bioimpedance.partials.segmental-body-map', ['measurements' => $segmentalMeasurements, 'type' => 'fat', 'title' => 'Gordura segmentar'])</td>
            <td>@include('bioimpedance.partials.segmental-body-map', ['measurements' => $segmentalMeasurements, 'type' => 'muscle', 'title' => 'Equilíbrio muscular'])</td>
        </tr></table>
        <table class="segmental-legend"><tr>
            <td><strong style="color:#2563eb;">↓ Abaixo</strong><br><span class="muted">Abaixo da faixa do equipamento</span></td>
            <td><strong style="color:#047857;">● Padrão</strong><br><span class="muted">Dentro da faixa do equipamento</span></td>
            <td><strong style="color:#be123c;">↑ Alto</strong><br><span class="muted">Acima da faixa do equipamento</span></td>
            <td><strong style="color:#475569;">? Não identificado</strong><br><span class="muted">Sem classificação reconhecida</span></td>
        </tr></table>
    </div>
</section>
@endif

@if($isBodyscan && $hasSegmentalComparison)
<section class="report-section">
    @include('bioimpedance.partials.report-header', ['pageTitle' => 'Evolução segmentar', 'pageNumber' => 3 + ($hasFitdaysDataPage ? 1 : 0) + ($hasDetailedComparison ? 1 : 0) + ($hasSegmentalPage ? 1 : 0), 'totalSections' => $totalSections])
    <div class="content">
        <div class="notice"><strong>Comparação segmentar.</strong> Os valores mostram mudanças numéricas e não classificam melhora, piora ou problema clínico.</div>
        @foreach(['fat' => 'Gordura segmentar', 'muscle' => 'Equilíbrio muscular'] as $segmentType => $segmentTitle)
            <div class="section-title" style="color: {{ $secondaryColor }};">{{ $segmentTitle }}</div>
            <table class="segmental-comparison">
                <thead><tr><th>Região</th><th>Massa anterior → atual</th><th>Variação</th><th>Referência anterior → atual</th><th>Variação</th></tr></thead>
                <tbody>
                @foreach(collect($segmentalComparison['regions'])->where('type', $segmentType) as $region)
                    <tr>
                        <td><strong>{{ $region['label'] }}</strong></td>
                        <td>{{ $br($region['mass_kg']['previous'], 2) }} kg → <strong>{{ $br($region['mass_kg']['current'], 2) }} kg</strong></td>
                        <td>{{ $region['mass_kg']['delta'] > 0 ? '+' : '' }}{{ $br($region['mass_kg']['delta'], 2) }} kg</td>
                        <td>{{ $br($region['previous_reference_percentage']) }}% → <strong>{{ $br($region['reference_percentage']['current']) }}%</strong></td>
                        <td>@if($region['reference_percentage']['delta'] !== null){{ $region['reference_percentage']['delta'] > 0 ? '+' : '' }}{{ $br($region['reference_percentage']['delta']) }} p.p.@else—@endif</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endforeach

        <div class="section-title" style="color: {{ $secondaryColor }};">Comparação entre os lados</div>
        <table class="segmental-comparison">
            <thead><tr><th>Medição</th><th>Região</th><th>Diferença absoluta anterior</th><th>Diferença absoluta atual</th><th>Referência anterior → atual</th></tr></thead>
            <tbody>
            @foreach($segmentalComparison['laterality'] as $balance)
                <tr>
                    <td>{{ $balance['type'] === 'fat' ? 'Gordura' : 'Músculo' }}</td>
                    <td><strong>{{ $balance['label'] }}</strong></td>
                    <td>{{ $br($balance['previous_mass_difference_kg'], 2) }} kg</td>
                    <td>{{ $br($balance['current_mass_difference_kg'], 2) }} kg</td>
                    <td>{{ $br($balance['previous_reference_difference']) }} → {{ $br($balance['current_reference_difference']) }} p.p.</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</section>
@endif

<section class="report-section page-guidance">
    @include('bioimpedance.partials.report-header', ['pageTitle' => 'Orientações', 'pageNumber' => $totalSections, 'totalSections' => $totalSections])
    <div class="content">
        <div class="section-title" style="color: {{ $secondaryColor }};">{{ $hasReviewedObservation ? 'Síntese revisada pelo profissional' : 'Resumo dos resultados' }}</div>
        <div class="summary">
            @if($assessment['notes'])
                <p style="white-space: pre-line;">{{ $assessment['notes'] }}</p>
            @else
                <p class="muted">Nenhuma observação profissional foi registrada.</p>
            @endif
        </div>
        @if(! ($assessment['care_guidance'] ?? null))
            <div class="section-title" style="color: {{ $secondaryColor }};">Acompanhamento</div>
            <div class="summary">Use esta avaliação como referência para o acompanhamento. Quando houver nova medição, procure manter horário, hidratação, alimentação recente e perfil de cálculo semelhantes para tornar a comparação mais consistente.</div>
        @endif
        <div class="section-title" style="color: {{ $secondaryColor }};">Orientações educativas</div>
        @if($assessment['care_guidance'] ?? null)
            @foreach($guidanceSections as $section)
                <div class="guidance-section"><div class="guidance-section-title">{{ $section['title'] }}</div><div>{{ $section['text'] }}</div></div>
            @endforeach
            <p class="muted" style="margin: 5px 2px 0;">Conteúdo educativo; não substitui avaliação médica, nutricional ou de educação física.</p>
        @else
            <div class="summary muted">Este campo é opcional e não recebeu orientação individualizada revisada para esta avaliação.</div>
        @endif
        <div class="report-closing">
            <div class="section-title" style="color: {{ $secondaryColor }};">Responsável e limites do relatório</div>
            <table class="validation">
                <tr><td style="width: 50%;"><div class="label">Profissional responsável</div><strong>{{ $responsibleProfessionalName }}</strong><br><span class="{{ $hasRecordedProfessionalReview ? 'review-status' : 'muted' }}">{{ $hasRecordedProfessionalReview ? 'Revisão registrada por '.$reviewedByName.' em '.\Illuminate\Support\Carbon::parse($reviewedAt)->format('d/m/Y H:i') : 'Sem revisão profissional identificada com responsável e data' }}</span></td><td><div class="label">Data da avaliação</div><strong>{{ \Illuminate\Support\Carbon::parse($assessment['evaluated_at'])->format('d/m/Y H:i') }}</strong></td></tr>
                <tr><td colspan="2" class="report-limits"><strong>Limites do relatório</strong><p>{{ $clinic['technical_notice'] }}</p></td></tr>
            </table>
        </div>
    </div>
</section>
</body>
</html>
