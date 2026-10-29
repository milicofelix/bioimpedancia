<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex,nofollow,noarchive">
    <meta name="referrer" content="no-referrer">
    <title>{{ $screeningSession->questionnaire_name }} · Vita Scan</title>
    <style>
        :root { color-scheme: light; font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; color: #17324d; background: #f5fbfa; }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; background: radial-gradient(circle at top right, #dff8ef 0, transparent 35%), linear-gradient(145deg, #f7fbff, #fff 55%, #f3fcf7); }
        main { width: min(100% - 32px, 720px); margin: 0 auto; padding: max(24px, env(safe-area-inset-top)) 0 max(32px, env(safe-area-inset-bottom)); }
        .brand { margin: 0 0 24px; color: #053c61; font-size: 1.6rem; font-weight: 850; letter-spacing: -.03em; }
        .brand span { color: #18a99a; } .brand small { display: block; margin-top: 2px; color: #506176; font-size: .68rem; font-weight: 700; letter-spacing: .16em; text-transform: uppercase; }
        .card { overflow: hidden; border: 1px solid #cfe9e3; border-radius: 24px; background: rgba(255,255,255,.96); box-shadow: 0 22px 60px rgba(18,75,87,.12); }
        .header { padding: 28px; color: white; background: linear-gradient(135deg, #063e62, #11998c); }
        h1 { margin: 0; font-size: clamp(1.65rem, 5vw, 2.35rem); line-height: 1.1; } .version { margin: 10px 0 0; font-size: .82rem; opacity: .82; }
        .content { padding: 28px; } h2 { margin: 0 0 12px; font-size: 1.15rem; } p { line-height: 1.6; }
        .notice { margin: 20px 0; padding: 16px; border-left: 4px solid #17a28f; border-radius: 12px; background: #effbf7; color: #27475a; }
        label { display: block; margin: 20px 0 8px; font-weight: 750; }
        input[type="date"], input[type="tel"], input[type="text"] { width: 100%; min-height: 52px; padding: 12px 14px; border: 1px solid #a9c8d2; border-radius: 12px; background: #fff; color: #17324d; font: inherit; font-size: 1rem; }
        input:focus-visible, button:focus-visible, .pdf-button:focus-visible { outline: 3px solid #62d6c4; outline-offset: 3px; }
        .consent { display: grid; grid-template-columns: 24px 1fr; gap: 10px; align-items: start; margin: 20px 0; line-height: 1.5; } .consent input { width: 20px; height: 20px; margin-top: 2px; }
        button { width: 100%; min-height: 52px; border: 0; border-radius: 14px; background: #087f73; color: #fff; font: inherit; font-weight: 800; cursor: pointer; } button:hover { background: #07695f; }
        .error { margin: 8px 0; color: #a51d35; font-weight: 700; } .attempts { color: #64748b; font-size: .82rem; }
        .error-summary { margin: 0 0 20px; padding: 16px; border: 2px solid #c94a61; border-radius: 14px; background: #fff1f3; color: #7f1730; }
        .error-summary strong { display: block; margin-bottom: 6px; }
        .error-summary ul { margin: 0; padding-left: 20px; }
        .success { display: grid; place-items: center; min-height: 220px; text-align: center; } .success-mark { display: grid; width: 64px; height: 64px; place-items: center; border-radius: 50%; background: #d9f8ed; color: #087f73; font-size: 2rem; font-weight: 900; }
        .result-score { margin: 18px 0 4px; color: #063e62; font-size: 2.25rem; font-weight: 900; line-height: 1; }
        .result-range { margin: 8px 0; color: #087f73; font-size: 1.2rem; font-weight: 850; }
        .trace { margin-top: 18px; color: #64748b; font-size: .76rem; }
        .pdf-button { display: inline-flex; min-height: 52px; margin-top: 8px; padding: 0 22px; align-items: center; justify-content: center; border-radius: 14px; background: #063e62; color: #fff; font-weight: 800; text-decoration: none; }
        .pdf-button:hover { background: #052f4b; }
        .instructions { margin: 0 0 20px; padding: 14px 16px; border-radius: 14px; background: #eef7fb; color: #29485d; }
        .question-list { display: grid; gap: 18px; margin: 24px 0; }
        .question { margin: 0; padding: 18px; border: 1px solid #d7e7e7; border-radius: 18px; background: #fff; }
        .question legend { padding: 0 4px; font-weight: 800; line-height: 1.45; }
        .option { display: grid; grid-template-columns: 22px 1fr; gap: 10px; align-items: center; min-height: 48px; margin: 8px 0 0; padding: 10px 12px; border: 1px solid #dce8ec; border-radius: 12px; background: #f9fcfc; font-weight: 600; cursor: pointer; }
        .option:has(input:checked) { border-color: #159889; background: #eafaf6; box-shadow: 0 0 0 2px #bcecdf; }
        .option input { width: 19px; height: 19px; margin: 0; accent-color: #087f73; }
        .visual-question { background: #f5fbfa; }
        .visual-options { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; margin-top: 14px; }
        .visual-option { display: grid; min-height: 150px; margin: 0; padding: 12px; place-items: center; border: 2px solid #d3e5e5; border-radius: 16px; background: #fff; cursor: pointer; }
        .visual-option:has(input:checked) { border-color: #087f73; background: #eafaf6; box-shadow: 0 0 0 3px #bcecdf; }
        .visual-option:has(input:focus-visible) { outline: 3px solid #62d6c4; outline-offset: 3px; }
        .visual-option input { position: absolute; width: 1px; height: 1px; overflow: hidden; opacity: 0; }
        .visual-option svg { width: 100%; max-width: 130px; height: 92px; color: #17324d; }
        .visual-option strong { color: #17324d; font-size: .95rem; }
        .visual-result { margin: 18px 0; padding: 18px; border: 1px solid #b9ddd5; border-radius: 16px; background: #f0fbf8; text-align: left; }
        .completion { margin: 20px 0; padding: 16px; border: 1px solid #b9ddd5; border-radius: 14px; background: #f0fbf8; }
        button:disabled { cursor: wait; opacity: .72; }
        footer { margin-top: 18px; color: #64748b; text-align: center; font-size: .78rem; }
    </style>
</head>
<body>
<main>
    <p class="brand">Vita <span>Scan</span><small>Análise clínica biológica</small></p>
    <article class="card">
        <header class="header">
            <h1>{{ $screeningSession->questionnaire_name }}</h1>
            <p class="version">Versão {{ $screeningSession->questionnaire_version }}</p>
        </header>
        <div class="content">
            @if ($completed)
                <section class="success" aria-live="polite">
                    <div>
                        <span class="success-mark" aria-hidden="true">✓</span>
                        <h2 style="margin-top: 18px">Questionário finalizado</h2>
                        <p>Suas respostas foram registradas com segurança e não podem ser enviadas novamente.</p>
                        <p class="result-score">{{ $screeningSession->total_score }} <small style="font-size: .9rem">de 30 pontos</small></p>
                        <p class="result-range">{{ $screeningSession->result_description }}</p>
                        <p>{{ $screeningSession->result_message }}</p>

                        @php($visualAnswer = $screeningSession->answers->firstWhere('question_code', 'VIS01'))
                        @if ($visualAnswer)
                            <div class="visual-result">
                                <strong>Reflexão associada à forma escolhida</strong>
                                <p>{{ $visualAnswer->response_label_snapshot }}</p>
                                <p class="attempts">Esta escolha é descritiva, não altera a pontuação e não diagnostica ansiedade ou qualquer condição de saúde.</p>
                            </div>
                        @endif

                        @php($attentionAnswers = $screeningSession->answers->where('requires_individual_attention', true))
                        @if ($attentionAnswers->isNotEmpty())
                            <div class="notice">
                                <strong>Respostas para conversar com o profissional</strong>
                                <p>Algumas respostas merecem ser conversadas individualmente com o profissional responsável.</p>
                                <p>{{ $screeningSession->attention_notice_snapshot ?: 'Estas respostas merecem conversa individual com profissional habilitado e, isoladamente, não constituem diagnóstico.' }}</p>
                            </div>
                        @else
                            <p class="notice"><strong>Próximo passo:</strong> converse com o profissional responsável para contextualizar este resultado com sua experiência e seu acompanhamento.</p>
                        @endif

                        <p class="notice"><strong>Importante:</strong> {{ $screeningSession->disclaimer_snapshot }}</p>
                        <a
                            class="pdf-button"
                            href="{{ route('screening.public.result-pdf', ['token' => request()->route('token')]) }}"
                            target="_blank"
                            rel="noopener noreferrer"
                        >Abrir resultado em PDF</a>
                        @if ($screeningSession->scoring_rules_version)
                            <p class="trace">Cálculo determinístico · regras {{ $screeningSession->scoring_rules_version }} · sem uso de inteligência artificial</p>
                        @endif
                    </div>
                </section>
            @elseif ($identityVerified)
                <section id="questionario" tabindex="-1" aria-labelledby="questionario-title">
                    <h2 id="questionario-title">Responda ao questionário</h2>
                    <p class="instructions">Nas perguntas de frequência, considere os últimos {{ $screeningSession->questionnaire->recall_period_days }} dias. Não existem respostas certas ou erradas. Selecione uma alternativa em cada item.</p>
                    <p class="attempts">Este acesso expira em {{ $screeningSession->expires_at?->format('d/m/Y \à\s H:i') }}.</p>

                    <form id="screening-form" method="post" action="{{ route('screening.public.complete', ['token' => request()->route('token')]) }}">
                    @csrf
                    <div class="question-list">
                        @foreach ($screeningSession->questionnaire->questions as $question)
                            <fieldset class="question {{ $question->response_type === 'descriptive_image_choice' ? 'visual-question' : '' }}">
                                <legend>{{ $question->position }}. {{ $question->text }}</legend>
                                @if ($question->response_type === 'descriptive_image_choice')
                                    <p class="attempts">{{ data_get($question->options->first()?->display_metadata, 'instructions') }}</p>
                                    <div class="visual-options">
                                        @foreach ($question->options as $option)
                                            @php($visualKey = $option->display_metadata['visual_key'] ?? '')
                                            <label class="visual-option" for="question-{{ $question->id }}-option-{{ $option->id }}">
                                                <input id="question-{{ $question->id }}-option-{{ $option->id }}" type="radio" name="answers[{{ $question->id }}]" value="{{ $option->id }}" required @checked((string) old("answers.{$question->id}") === (string) $option->id)>
                                                <svg viewBox="0 0 120 90" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                                                    @switch($visualKey)
                                                        @case('grid_dense')
                                                            @foreach ([18,32,46,60,74,88,102] as $x)<path d="M{{ $x }} 10 Q{{ $x - 4 }} 45 {{ $x }} 80" />@endforeach
                                                            @foreach ([16,29,42,55,68,81] as $y)<path d="M10 {{ $y }} Q60 {{ $y - 4 }} 110 {{ $y }}" />@endforeach
                                                            @break
                                                        @case('rings_irregular')
                                                            <path d="M60 8 C91 7 111 28 106 52 C101 76 79 86 55 82 C29 79 12 63 15 39 C18 17 37 7 60 8Z"/><path d="M59 19 C81 16 96 31 94 50 C92 68 75 75 57 72 C39 70 27 59 29 43 C31 28 43 20 59 19Z"/><path d="M59 31 C72 29 83 38 82 49 C81 61 70 65 59 63 C48 61 42 54 43 45 C44 36 51 32 59 31Z"/><path d="M59 41 C66 40 71 44 71 50 C70 56 65 58 59 57 C54 56 51 53 52 48 C52 44 55 42 59 41Z"/>
                                                            @break
                                                        @case('grid_irregular')
                                                            @foreach ([22,38,55,73,92] as $x)<path d="M{{ $x }} 12 L{{ $x + 7 }} 79" />@endforeach
                                                            @foreach ([18,35,54,72] as $y)<path d="M13 {{ $y + 6 }} L108 {{ $y }}" />@endforeach
                                                            @break
                                                        @case('spirals_group')
                                                            <path d="M25 28 C7 28 8 8 25 10 C42 12 40 31 27 35 C16 39 10 52 21 59"/><path d="M65 22 C47 22 48 3 65 6 C82 9 79 28 67 32 C55 36 53 50 64 55"/><path d="M99 29 C82 29 84 11 100 13 C114 15 113 33 102 37 C92 41 88 54 99 60"/><path d="M46 62 C30 62 31 45 46 47 C60 49 59 65 49 69 C40 72 38 82 46 84"/><path d="M82 69 C67 69 68 53 82 55 C95 57 94 72 85 75 C77 78 75 85 81 87"/>
                                                            @break
                                                        @case('scribble')
                                                            <path d="M60 45 C18 2 5 80 61 79 C110 78 109 8 55 11 C8 13 15 75 70 70 C108 66 101 18 57 20 C24 21 26 66 68 62 C95 59 89 29 58 29 C36 30 39 57 65 54 C80 52 77 38 59 38 C50 39 52 48 62 47"/>
                                                            @break
                                                        @case('squares')
                                                            @foreach ([[12,14],[43,8],[75,15],[96,7],[24,40],[57,35],[87,43],[10,67],[46,63],[78,68],[101,62]] as $p)<rect x="{{ $p[0] }}" y="{{ $p[1] }}" width="17" height="17" />@endforeach
                                                            @break
                                                        @case('spiral_open')
                                                            <path d="M107 13 C82 2 43 8 23 32 C1 59 25 84 59 81 C91 78 108 52 96 31 C86 13 55 15 40 32 C27 47 39 67 59 67 C77 66 87 52 81 40 C75 29 58 30 51 39 C45 47 51 56 60 55 C67 55 70 49 68 45"/>
                                                            @break
                                                        @case('zigzag')
                                                            <path d="M12 78 L20 12 L28 78 L36 12 L44 78 L52 12 L60 78 L68 12 L76 78 L84 12 L92 78 L100 12 L108 78"/>
                                                            @break
                                                    @endswitch
                                                </svg>
                                                <strong>{{ $option->display_metadata['choice_label'] ?? 'Imagem '.$option->value }}</strong>
                                            </label>
                                        @endforeach
                                    </div>
                                @else
                                    @foreach ($question->options as $option)
                                        <label class="option" for="question-{{ $question->id }}-option-{{ $option->id }}">
                                            <input id="question-{{ $question->id }}-option-{{ $option->id }}" type="radio" name="answers[{{ $question->id }}]" value="{{ $option->id }}" required @checked((string) old("answers.{$question->id}") === (string) $option->id)>
                                            <span>{{ $option->label }}</span>
                                        </label>
                                    @endforeach
                                @endif
                            </fieldset>
                        @endforeach
                    </div>

                    @error('answers')<p class="error" role="alert">{{ $message }}</p>@enderror
                    @error('answers.*')<p class="error" role="alert">{{ $message }}</p>@enderror
                    @error('session')<p class="error" role="alert">{{ $message }}</p>@enderror

                    <div class="completion">
                        <label class="consent" for="completion_confirmed" style="margin: 0">
                            <input id="completion_confirmed" name="completion_confirmed" type="checkbox" value="1" required @checked(old('completion_confirmed'))>
                            <span>Revisei minhas respostas e confirmo o envio definitivo deste questionário.</span>
                        </label>
                        @error('completion_confirmed')<p class="error" role="alert">{{ $message }}</p>@enderror
                    </div>
                    <button id="screening-submit" type="submit">Finalizar e enviar respostas</button>
                    </form>
                </section>
            @else
                <section id="confirmacao" tabindex="-1" aria-labelledby="confirmacao-title">
                <h2 id="confirmacao-title">Confirmação de acesso</h2>
                <p>Antes de iniciar, confirme uma informação pessoal. Ela será usada apenas para verificar se este convite pertence a você.</p>
                @if ($errors->any())
                    <div class="error-summary" role="alert" aria-live="assertive">
                        <strong>Não foi possível continuar:</strong>
                        <ul>
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
                @if ($screeningSession->is_minor_at_application)
                    <div class="notice">
                        <strong>Preenchimento de menor de idade</strong>
                        <p>Este convite exige a identificação e o consentimento do responsável legal cadastrado pelo profissional.</p>
                    </div>
                @endif
                <div class="notice">
                    <strong>Consentimento específico, privacidade e limites</strong>
                    <p>{{ $screeningSession->disclaimer_snapshot }}</p>
                    <p>As respostas serão usadas exclusivamente para esta triagem e acompanhamento profissional, conforme o consentimento abaixo. O questionário não produz diagnóstico.</p>
                </div>

                <form id="identity-form" method="post" action="{{ route('screening.public.verify', ['token' => request()->route('token')]) }}">
                    @csrf
                    <label for="identity_confirmation">{{ $identityPrompt['label'] }}</label>
                    <input id="identity_confirmation" name="identity_confirmation" type="{{ $identityPrompt['type'] }}" placeholder="{{ $identityPrompt['placeholder'] }}" required autocomplete="off" value="{{ old('identity_confirmation') }}">
                    @error('identity_confirmation')<p class="error" role="alert">{{ $message }}</p>@enderror
                    <p class="attempts">Restam {{ $remainingAttempts }} tentativa(s) de confirmação.</p>

                    @if ($screeningSession->is_minor_at_application)
                        <label for="guardian_name">Nome completo do responsável legal</label>
                        <input id="guardian_name" name="guardian_name" type="text" maxlength="160" required autocomplete="name" value="{{ old('guardian_name') }}">
                        @error('guardian_name')<p class="error" role="alert">{{ $message }}</p>@enderror

                        <label class="consent" for="guardian_consent">
                            <input id="guardian_consent" name="guardian_consent" type="checkbox" value="yes" required @checked(old('guardian_consent'))>
                            <span>{{ $screeningSession->guardian_consent_text_snapshot }}</span>
                        </label>
                        @error('guardian_consent')<p class="error" role="alert">{{ $message }}</p>@enderror
                    @endif

                    <label class="consent" for="consent">
                        <input id="consent" name="consent" type="checkbox" value="yes" required @checked(old('consent'))>
                        <span>{{ $screeningSession->consent_text_snapshot }}</span>
                    </label>
                    @error('consent')<p class="error" role="alert">{{ $message }}</p>@enderror
                    <button id="identity-submit" type="submit">Confirmar e continuar</button>
                </form>
                </section>
            @endif
        </div>
    </article>
    <footer>Este acesso é temporário, individual e não permite entrar no restante do sistema.</footer>
</main>
<script>
    if (['#questionario', '#confirmacao'].includes(window.location.hash)) {
        window.history.scrollRestoration = 'manual';
        window.requestAnimationFrame(function () {
            const target = document.querySelector(window.location.hash);
            target?.scrollIntoView({ block: 'start' });
            target?.focus({ preventScroll: true });
        });
    }

    document.getElementById('identity-form')?.addEventListener('submit', function () {
        ['consent', 'guardian_consent'].forEach(function (fieldId) {
            const checkbox = document.getElementById(fieldId);

            if (checkbox?.checked) {
                checkbox.value = 'yes';
            }
        });

        const button = document.getElementById('identity-submit');
        if (!button || button.disabled) return;
        button.disabled = true;
        button.textContent = 'Confirmando acesso…';
    });

    document.getElementById('screening-form')?.addEventListener('submit', function () {
        const button = document.getElementById('screening-submit');
        if (!button || button.disabled) return;
        button.disabled = true;
        button.textContent = 'Enviando respostas…';
    });
</script>
</body>
</html>
