# Checklist de liberação

Preencher antes de cada publicação relevante. Itens não aplicáveis devem possuir justificativa registrada.

## Segurança e privacidade

- [ ] `.env` e segredos não estão no pacote.
- [ ] `APP_ENV=production`, `APP_DEBUG=false`, HTTPS e cookies seguros estão configurados.
- [ ] Matriz de permissões foi revisada para as mudanças da versão.
- [ ] Checklist LGPD foi revisado e impactos de novos tratamentos foram aprovados.
- [ ] Uploads, links públicos e integrações externas mantêm limites e expiração.

## Dados e recuperação

- [ ] Backup criptografado anterior ao deploy foi criado e validado por checksum.
- [ ] Migration possui estratégia de rollback ou recuperação documentada.
- [ ] Último exercício de restauração está dentro da periodicidade definida.
- [ ] Espaço, retenção e cópia externa foram conferidos.

## Qualidade e acessibilidade

- [ ] Testes backend, frontend e E2E previstos foram concluídos no pipeline autorizado.
- [ ] Fluxos manuais críticos foram revisados em desktop e PWA.
- [ ] Checklist de acessibilidade foi preenchido e barreiras críticas foram resolvidas.
- [ ] PDF foi conferido com e sem conteúdo opcional.
- [ ] Não existem textos de teste, dados demo ou marcas legadas visíveis.

## Operação

- [ ] `/up` e `/up/ready` respondem no ambiente de destino.
- [ ] Logs, alertas, disco, certificado, scheduler e backups estão monitorados.
- [ ] Responsável pelo deploy e responsável por rollback estão definidos.
- [ ] Janela e comunicação foram aprovadas.
- [ ] Procedimento de incidente está acessível à equipe.

## Pós-deploy

- [ ] Login, seleção de cliente, avaliação, OCR, relatório e compartilhamento foram verificados com dados sintéticos.
- [ ] Erros 5xx e latência foram observados após a publicação.
- [ ] Scheduler, filas e integrações responderam corretamente.
- [ ] Versão, horário, responsável e evidências foram registrados.
- [ ] Decisão de manter ou reverter foi formalizada.

Referências: `docs/permissions-matrix.md`, `docs/operations/backup-and-restore.md`, `docs/operations/observability-and-incidents.md`, `docs/accessibility-checklist.md` e `docs/lgpd-checklist.md`.
