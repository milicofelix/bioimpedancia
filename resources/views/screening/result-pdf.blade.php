<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Resultado da triagem · Vita Scan</title>
    <style>
        @page { margin: 34px 42px; }
        * { box-sizing: border-box; }
        body { margin: 0; color: #17324d; font-family: DejaVu Sans, sans-serif; font-size: 12px; line-height: 1.55; }
        .brand { margin-bottom: 24px; padding-bottom: 16px; border-bottom: 2px solid #17a28f; }
        .brand-name { color: #063e62; font-size: 25px; font-weight: bold; }
        .brand-name span { color: #11998c; }
        .brand-subtitle { color: #506176; font-size: 9px; letter-spacing: 2px; text-transform: uppercase; }
        h1 { margin: 0 0 5px; color: #063e62; font-size: 21px; }
        .meta { margin: 0 0 24px; color: #64748b; font-size: 10px; }
        .result { margin: 22px 0; padding: 24px; border: 1px solid #b9ddd5; border-radius: 14px; background: #f0fbf8; text-align: center; }
        .score { margin: 0; color: #063e62; font-size: 34px; font-weight: bold; }
        .range { margin: 6px 0 12px; color: #087f73; font-size: 17px; font-weight: bold; }
        .section { margin-top: 18px; padding: 16px; border-left: 4px solid #17a28f; background: #f5fbfa; }
        .section h2 { margin: 0 0 6px; color: #063e62; font-size: 13px; }
        .important { border-left-color: #4b6b82; background: #f4f7f9; }
        .trace { margin-top: 26px; padding-top: 12px; border-top: 1px solid #d8e4e8; color: #64748b; font-size: 9px; }
        footer { position: fixed; right: 0; bottom: -18px; left: 0; color: #718096; font-size: 8px; text-align: center; }
    </style>
</head>
<body>
    <div class="brand">
        <div class="brand-name">Vita <span>Scan</span></div>
        <div class="brand-subtitle">Análise clínica biológica</div>
    </div>

    <h1>{{ $screeningSession->questionnaire_name }}</h1>
    <p class="meta">Versão {{ $screeningSession->questionnaire_version }} · concluído em {{ $screeningSession->finalized_at?->format('d/m/Y \à\s H:i') }}</p>

    <div class="result">
        <p class="score">{{ $screeningSession->total_score }} <span style="font-size: 13px">de 30 pontos</span></p>
        <p class="range">{{ $screeningSession->result_description }}</p>
        <p>{{ $screeningSession->result_message }}</p>
    </div>

    @if ($hasAttentionAnswers)
        <div class="section">
            <h2>Conversa individual recomendada</h2>
            <p>Algumas respostas merecem ser conversadas individualmente com o profissional responsável.</p>
            <p>{{ $screeningSession->attention_notice_snapshot ?: 'Uma resposta isolada não constitui diagnóstico e deve ser contextualizada por profissional habilitado.' }}</p>
        </div>
    @else
        <div class="section">
            <h2>Acompanhamento</h2>
            <p>Converse com o profissional responsável para contextualizar este resultado com sua experiência e seu acompanhamento.</p>
        </div>
    @endif

    <div class="section important">
        <h2>Limites desta devolutiva</h2>
        <p>{{ $screeningSession->disclaimer_snapshot }}</p>
        <p>Este resultado é descritivo e não substitui entrevista, avaliação clínica ou instrumento validado.</p>
    </div>

    <p class="trace">Cálculo determinístico@if ($screeningSession->scoring_rules_version) · regras {{ $screeningSession->scoring_rules_version }}@endif · sem uso de inteligência artificial · PDF emitido em {{ $issuedAt->format('d/m/Y \à\s H:i') }}</p>
    <footer>Documento de devolutiva descritiva · Vita Scan</footer>
</body>
</html>
