# Validacoes Do Projeto

Projeto: Ricostyemagrecimento
Data: 2026-08-09

## Objetivo

Registrar os comandos oficiais de validacao para que pacotes futuros nao sejam gerados sem checklist verificavel.

## Validacao Principal

Preferir Docker quando o ambiente local nao tiver PHP, Composer ou Node disponiveis.

```bash
docker compose exec -T app composer test
docker compose run --rm frontend_dev npm run build
docker compose ps
```

## Validacao Alternativa Local

Use quando as dependencias locais estiverem instaladas.

```bash
php artisan test
npm run build
```

## Qualidade E Seguranca

```bash
./vendor/bin/pint --dirty
docker compose exec -T app composer audit
docker compose run --rm frontend_dev npm audit --audit-level=moderate
```

## Verificacoes Laravel Uteis

```bash
php artisan route:list
php artisan config:clear
php artisan route:clear
php artisan view:clear
```

Antes de producao, tambem validar cache:

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan config:clear
php artisan route:clear
php artisan view:clear
```

## Regras Para Registrar Resultado

Ao finalizar uma execucao, registrar:

- Comando executado.
- Resultado: passou, falhou ou nao executado.
- Motivo quando nao executado.
- Quantidade de testes/assertions quando disponivel.
- Risco residual se houver falha ou validacao pendente.

## Comandos Que Exigem Confirmacao Manual

Nao executar automaticamente sem autorizacao explicita:

```bash
php artisan migrate --force
php artisan db:seed
docker compose down -v
docker volume rm
git reset --hard
git clean -fd
```

## Ultima Validacao Conhecida

Referencia da ultima rodada registrada em conversa:

- `php artisan test`: 40 testes passaram, 373 assertions.
- `npm run build`: passou.

Esses resultados sao historicos e nao substituem nova validacao apos alteracoes.
