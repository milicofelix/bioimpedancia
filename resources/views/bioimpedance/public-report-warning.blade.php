<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Confirmação de acesso ao relatório</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; padding: 24px; background: #f8fafc; color: #0f172a; font-family: system-ui, sans-serif; }
        main { width: min(100%, 440px); padding: 28px; border: 1px solid #fcd34d; border-radius: 20px; background: white; box-shadow: 0 12px 35px rgba(15, 23, 42, .08); }
        .badge { color: #92400e; font-size: 12px; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; }
        h1 { margin: 10px 0; font-size: 24px; }
        p { color: #475569; font-size: 15px; line-height: 1.6; }
        .warning { margin: 20px 0; padding: 14px; border-radius: 12px; background: #fffbeb; color: #78350f; font-size: 14px; line-height: 1.5; }
        a { display: block; padding: 14px 18px; border-radius: 12px; background: #0f172a; color: white; font-weight: 800; text-align: center; text-decoration: none; }
        small { display: block; margin-top: 14px; color: #64748b; text-align: center; }
    </style>
</head>
<body>
<main>
    <div class="badge">Conteúdo confidencial</div>
    <h1>Relatório com dados sensíveis</h1>
    <p>Este link abrirá um relatório em PDF com informações pessoais e clínicas. Confirme que você é o destinatário correto e está em um ambiente privado antes de continuar.</p>
    <div class="warning">Não encaminhe o link sem autorização. Feche a página ao terminar, especialmente em aparelhos compartilhados.</div>
    <a href="{{ $confirmUrl }}" rel="nofollow">Confirmar e abrir PDF</a>
    <small>Link válido até {{ $expiresAt->format('d/m/Y H:i') }}.</small>
</main>
</body>
</html>
