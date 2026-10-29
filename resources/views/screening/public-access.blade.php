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
        input[type="date"], input[type="tel"] { width: 100%; min-height: 52px; padding: 12px 14px; border: 1px solid #a9c8d2; border-radius: 12px; background: #fff; color: #17324d; font: inherit; font-size: 1rem; }
        input:focus-visible, button:focus-visible { outline: 3px solid #62d6c4; outline-offset: 3px; }
        .consent { display: grid; grid-template-columns: 24px 1fr; gap: 10px; align-items: start; margin: 20px 0; line-height: 1.5; } .consent input { width: 20px; height: 20px; margin-top: 2px; }
        button { width: 100%; min-height: 52px; border: 0; border-radius: 14px; background: #087f73; color: #fff; font: inherit; font-weight: 800; cursor: pointer; } button:hover { background: #07695f; }
        .error { margin: 8px 0; color: #a51d35; font-weight: 700; } .attempts { color: #64748b; font-size: .82rem; }
        .success { display: grid; place-items: center; min-height: 220px; text-align: center; } .success-mark { display: grid; width: 64px; height: 64px; place-items: center; border-radius: 50%; background: #d9f8ed; color: #087f73; font-size: 2rem; font-weight: 900; }
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
            @if ($identityVerified)
                <section class="success" aria-live="polite">
                    <div>
                        <span class="success-mark" aria-hidden="true">✓</span>
                        <h2 style="margin-top: 18px">Acesso confirmado</h2>
                        <p>Sua identidade e seu consentimento foram registrados com segurança.</p>
                        <p class="notice">As perguntas serão disponibilizadas na próxima etapa deste módulo. Não compartilhe este aparelho enquanto a aplicação estiver aberta.</p>
                    </div>
                </section>
            @else
                <h2>Confirmação de acesso</h2>
                <p>Antes de iniciar, confirme uma informação pessoal. Ela será usada apenas para verificar se este convite pertence a você.</p>
                <div class="notice"><strong>Aviso de privacidade e limites</strong><br>{{ $screeningSession->disclaimer_snapshot }}</div>

                <form method="post" action="{{ route('screening.public.verify', ['token' => request()->route('token')]) }}">
                    @csrf
                    <label for="identity_confirmation">{{ $identityPrompt['label'] }}</label>
                    <input id="identity_confirmation" name="identity_confirmation" type="{{ $identityPrompt['type'] }}" placeholder="{{ $identityPrompt['placeholder'] }}" required autocomplete="off" value="{{ old('identity_confirmation') }}">
                    @error('identity_confirmation')<p class="error" role="alert">{{ $message }}</p>@enderror
                    <p class="attempts">Restam {{ $remainingAttempts }} tentativa(s) de confirmação.</p>

                    <label class="consent" for="consent">
                        <input id="consent" name="consent" type="checkbox" value="1" required @checked(old('consent'))>
                        <span>{{ $screeningSession->consent_text_snapshot }}</span>
                    </label>
                    @error('consent')<p class="error" role="alert">{{ $message }}</p>@enderror
                    <button type="submit">Confirmar e continuar</button>
                </form>
            @endif
        </div>
    </article>
    <footer>Este acesso é temporário, individual e não permite entrar no restante do sistema.</footer>
</main>
</body>
</html>
