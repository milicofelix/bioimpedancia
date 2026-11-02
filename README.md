# Vita Scan — Análise clínica Biológica

Sistema Laravel + React para cadastro de clientes, avaliações corporais com múltiplos equipamentos, relatório PDF profissional, histórico, compartilhamento seguro, triagens e assistentes fundamentados em referências aprovadas.

## Stack

- PHP 8.2
- Laravel 12
- React 19
- Vite
- Tailwind CSS 4
- MySQL em produção
- DomPDF para relatório oficial
- Docker Compose para desenvolvimento local

## Execução Local

1. Copie o ambiente:

```bash
cp .env.example .env
```

2. Configure o banco no `.env`.

3. Suba os containers:

```bash
docker compose up -d
```

4. Instale dependências, gere chave e rode migrations com dados demo:

```bash
docker compose exec -T app composer install
docker compose exec -T app php artisan key:generate
docker compose exec -T app php artisan migrate --seed
```

5. Acesse:

```text
http://localhost:8081/login
```

Usuários demo:

```text
Admin: valor de VITA_SCAN_DEMO_ADMIN_EMAIL
Profissional: valor de VITA_SCAN_DEMO_PROFESSIONAL_EMAIL
Recepção: valor de VITA_SCAN_DEMO_RECEPTION_EMAIL
Senha: valor de VITA_SCAN_DEMO_PASSWORD
```

Esses usuários demo são ignorados automaticamente quando `APP_ENV=production`.
Defina `VITA_SCAN_DEMO_PASSWORD` no `.env` local antes de rodar `php artisan migrate --seed`.

## Desenvolvimento Frontend

```bash
docker compose exec -T frontend_dev npm run dev -- --host 0.0.0.0 --port 5153
```

Build de produção:

```bash
docker compose exec -T frontend_dev npm run build
```

## Testes

```bash
php artisan test
```

ou, no container:

```bash
docker compose exec -T app php artisan test
```

## Preparação Para Deploy

Antes de publicar:

```bash
scripts/production-check.sh
```

Para gerar pacote limpo:

```bash
scripts/build-release.sh
```

O pacote é montado somente com arquivos rastreados ou não ignorados pelo Git. Antes de distribuí-lo, revise seu conteúdo e confirme que `.env`, bancos locais, logs, caches e outros arquivos sensíveis permanecem ignorados.

## Variáveis Críticas de Produção

Use valores próprios no servidor:

```text
APP_ENV=production
APP_DEBUG=false
APP_URL=https://seudominio.com.br
APP_KEY=base64:...
DB_CONNECTION=mysql
SESSION_SECURE_COOKIE=true
SESSION_ENCRYPT=true
LOG_LEVEL=warning
MAIL_MAILER=smtp
OPENAI_API_KEY=chave_producao
OPENAI_MODEL=gpt-5.1
OPENAI_BASE_URL=https://api.openai.com/v1
OPENAI_TIMEOUT=45
BIOIMPEDANCE_ASSISTANT_PROVIDER=hybrid
```

Modos do assistente:

```text
hybrid: usa OpenAI quando OPENAI_API_KEY existe; se falhar, usa o motor local.
openai: exige OpenAI; sem chave ou com erro de API, bloqueia a sugestão.
local_reference_engine: usa somente o motor local fundamentado em referências parametrizadas.
```

Depois do deploy:

```bash
php artisan migrate --force
php artisan vita-scan:create-admin
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## Segurança

- Não versionar `.env`.
- Não versionar `database/*.sqlite`.
- Não enviar `storage/logs/*.log`.
- Não publicar `storage/framework/*`.
- Usar HTTPS em produção.
- Manter `APP_DEBUG=false`.
- Não executar `php artisan db:seed` em produção.
- Criar o primeiro administrador com `php artisan vita-scan:create-admin`.
- Rodar backup de banco e storage antes de atualizar produção.
- Revisar permissões de `storage` e `bootstrap/cache`.

## Compatibilidade de nomenclatura

- Variáveis `RICOSTY_DEMO_*` ainda são aceitas como fallback durante a transição, mas novas configurações devem usar `VITA_SCAN_DEMO_*`.
- O comando `ricosty:create-admin` permanece como alias temporário; use `vita-scan:create-admin` em novos procedimentos.
- Prefixos antigos de cache, preferências do navegador, migrations históricas, nomes de classes CSS e o arquivo físico `ricosty-logo.png` são mantidos apenas para compatibilidade. Eles não representam a marca exibida ao usuário.

## Commit Sugerido Por Fase

Use mensagens no padrão:

```bash
git c "feat: describe change"
```
