# Observabilidade e resposta a incidentes

## Sinais disponíveis

- `GET /up`: processo da aplicação respondendo.
- `GET /up/ready`: banco e cache disponíveis; retorna HTTP 503 quando a prontidão falha.
- Cabeçalho `X-Request-ID`: correlação entre navegador, proxy e logs.
- Contexto técnico de log `request_id`, sem nome, e-mail, token ou conteúdo clínico.
- `AppAudit`: trilha funcional para ações clínicas e administrativas. Não substitui logs técnicos.

O endpoint de prontidão é limitado por requisições e não expõe credenciais, nomes de tabelas ou detalhes de exceções.

## Configuração recomendada

Em produção:

```text
APP_ENV=production
APP_DEBUG=false
LOG_CHANNEL=stack
LOG_STACK=daily
LOG_LEVEL=warning
LOG_DAILY_DAYS=30
```

Encaminhe logs a um coletor externo quando disponível. O transporte deve usar TLS, controle de acesso, retenção definida e região compatível com a política de privacidade.

## Monitoramento mínimo

| Sinal | Frequência | Alerta sugerido |
| --- | --- | --- |
| `/up` | 1 minuto | 2 falhas consecutivas |
| `/up/ready` | 1 minuto | 2 falhas consecutivas |
| HTTP 5xx | contínuo | aumento por 5 minutos ou qualquer pico após deploy |
| Latência p95 | contínuo | acima de 2 s por 10 minutos |
| OCR/assistente externo | contínuo | erro ou timeout acima de 10% em 15 minutos |
| Espaço em disco | 5 minutos | 80% aviso; 90% crítico |
| Backup | diário | ausência, falha ou checksum inválido |
| Scheduler | diário | retenção não executada no horário previsto |
| Certificado TLS | diário | menos de 30 dias para expirar |

Os limites devem ser recalibrados com dados reais, sem reduzir alertas apenas para ocultar ruído.

## Verificação externa

```bash
scripts/operational-readiness-check.sh https://sistema.exemplo
```

Não use cliente ou token real em sondas. A verificação precisa permanecer somente técnica.

## Logs proibidos

- Senhas, chaves de API, cookies, tokens públicos ou CSRF.
- Imagens enviadas para OCR ou texto integral extraído.
- Nome, CPF, telefone, e-mail ou endereço de cliente.
- Respostas integrais do assistente contendo dados clínicos.
- Dumps de requisição, banco ou sessão.

Quando for necessário diagnosticar um registro, use identificador interno, `request_id` e auditoria autorizada.

## Classificação de incidente

- **SEV-1:** indisponibilidade total, exposição confirmada ou perda relevante de dados.
- **SEV-2:** função clínica crítica indisponível, corrupção localizada ou risco de exposição.
- **SEV-3:** degradação sem perda, falha contornável ou problema visual relevante.

## Resposta

1. Detectar e registrar horário, origem e responsável.
2. Conter: bloquear compartilhamentos, credenciais ou integração afetada quando necessário.
3. Preservar logs e auditorias sem copiar dados para canais não autorizados.
4. Avaliar impacto, titulares, período e sistemas envolvidos.
5. Recuperar usando procedimento aprovado e validar antes de reabrir.
6. Comunicar internamente e avaliar obrigações perante titulares e ANPD com o encarregado/jurídico.
7. Produzir análise de causa, ações corretivas e prazo.
8. Confirmar que credenciais temporárias e ambientes de investigação foram encerrados.

Nunca registre dados clínicos em tickets, chats ou e-mails sem canal e autorização adequados.
