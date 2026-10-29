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
        .visual-choice { border-left-color: #087f73; background: #eefaf7; page-break-inside: avoid; }
        .visual-choice-number { margin: 0 0 4px; color: #087f73; font-size: 10px; font-weight: bold; text-transform: uppercase; }
        .visual-choice-title { margin: 0 0 8px; color: #063e62; font-size: 15px; font-weight: bold; }
        .visual-choice-layout { width: 100%; margin: 8px 0 0; border-collapse: collapse; }
        .visual-choice-layout td { padding: 0; border: 0; vertical-align: middle; }
        .visual-choice-thumbnail { width: 136px; padding-right: 16px !important; color: #087f73; text-align: center; }
        .visual-choice-thumbnail img { display: block; width: 112px; height: 84px; margin: 0 auto; }
        .important { border-left-color: #4b6b82; background: #f4f7f9; }
        table { width: 100%; margin-top: 12px; border-collapse: collapse; }
        th, td { padding: 9px 8px; border-bottom: 1px solid #d8e4e8; text-align: left; vertical-align: top; }
        th { color: #506176; font-size: 9px; text-transform: uppercase; }
        .answer { width: 28%; font-weight: bold; }
        tr { page-break-inside: avoid; }
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
    <p class="meta">
        Cliente: {{ $screeningSession->client->full_name }}<br>
        Preenchimento concluído em {{ $screeningSession->finalized_at?->format('d/m/Y \à\s H:i') }}<br>
        Questionário {{ $screeningSession->questionnaire_version }} · solicitado por {{ $screeningSession->requested_by_name ?: 'Profissional responsável' }}
    </p>

    <div class="result">
        <p class="score">{{ $screeningSession->total_score }} <span style="font-size: 13px">de 30 pontos</span></p>
        <p class="range">{{ $screeningSession->result_description }}</p>
        <p>{{ $screeningSession->result_message }}</p>
    </div>

    <div class="section">
        <h2>Respostas informadas</h2>
        @php($visualAnswer = $screeningSession->answers->firstWhere('question_code', 'VIS01'))
        <table>
            <thead><tr><th>Pergunta</th><th class="answer">Resposta</th></tr></thead>
            <tbody>
                @foreach ($screeningSession->answers as $answer)
                    @continue($answer->question_code === 'VIS01')
                    <tr>
                        <td>{{ $answer->question_text_snapshot }}</td>
                        <td class="answer">{{ $answer->response_label_snapshot }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @if ($visualAnswer)
        <div class="section visual-choice">
            <h2>Resultado da imagem escolhida</h2>
            <table class="visual-choice-layout" role="presentation">
                <tr>
                    <td class="visual-choice-thumbnail">
                        @if ($visualChoiceThumbnail)
                            <img
                                src="{{ $visualChoiceThumbnail }}"
                                width="112"
                                height="84"
                                alt="Imagem {{ $visualAnswer->response_value }}: {{ $visualChoiceResult['title'] ?? 'escolha visual' }}"
                            >
                        @endif
                        <p class="visual-choice-number">Imagem {{ $visualAnswer->response_value }}</p>
                    </td>
                    <td>
                        <p class="visual-choice-title">{{ $visualChoiceResult['title'] ?? 'Escolha visual descritiva' }}</p>
                        <p>{{ $visualChoiceResult['description'] ?? $visualAnswer->response_label_snapshot }}</p>
                    </td>
                </tr>
            </table>
            <p>Esta reflexão não altera a pontuação e não possui finalidade diagnóstica.</p>
        </div>
    @endif

    <div class="section">
        <h2>Acompanhamento</h2>
        <p>Converse com o profissional responsável para contextualizar este resultado com sua experiência e seu acompanhamento.</p>
    </div>

    <div class="section important">
        <h2>Limites desta devolutiva</h2>
        <p>{{ $screeningSession->disclaimer_snapshot }}</p>
        <p>Este resultado é descritivo e não substitui entrevista, avaliação clínica ou instrumento validado.</p>
    </div>

    <p class="trace">Documento {{ $screeningSession->document_authenticity_code }} · emitido em {{ $issuedAt->format('d/m/Y \à\s H:i') }}</p>
    <footer>Documento de devolutiva descritiva · Vita Scan · {{ $screeningSession->document_authenticity_code }}</footer>
</body>
</html>
