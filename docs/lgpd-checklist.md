# Checklist LGPD

Este documento é um controle técnico-organizacional e não constitui parecer jurídico nem declaração automática de conformidade. Bases legais, prazos e comunicações devem ser validados pelo controlador, encarregado e assessoria jurídica.

## Governança

- [ ] Controlador, operadores, encarregado e canais de contato estão definidos.
- [ ] Inventário de dados e fluxo entre web, PWA, banco, e-mail, OCR e assistente está atualizado.
- [ ] Finalidade e base legal estão registradas por tratamento.
- [ ] Relatório de impacto/RIPD foi avaliado para dados de saúde e uso de IA.
- [ ] Contratos com operadores definem segurança, suboperadores, retenção e incidente.
- [ ] Transferência internacional, inclusive provedores de IA/cloud, foi avaliada.

## Minimização e transparência

- [ ] Apenas dados necessários são coletados.
- [ ] Aviso de privacidade informa finalidade, compartilhamentos, retenção e direitos.
- [ ] Consentimento, quando for a base aplicável, é específico, demonstrável e revogável.
- [ ] Dados do cliente não são reutilizados para treinamento ou finalidade incompatível sem autorização/base adequada.
- [ ] Imagens de OCR são eliminadas após o processamento conforme o fluxo documentado.
- [ ] Mensagens ao cliente evitam dados clínicos e utilizam link temporário revogável.

## Acesso e segurança

- [ ] Matriz de permissões foi revisada e testada.
- [ ] Contas individuais, senhas fortes e inativação imediata são exigidas.
- [ ] Privilégios são revistos periodicamente e após mudança de função.
- [ ] HTTPS, cookies seguros, sessão e proteção CSRF estão habilitados em produção.
- [ ] Segredos não estão no repositório, logs ou backups sem proteção.
- [ ] Ações clínicas, administrativas e compartilhamentos possuem auditoria.
- [ ] Logs técnicos não contêm dados pessoais ou conteúdo clínico.

## Direitos dos titulares

- [ ] Existe procedimento autenticado para confirmação de identidade do solicitante.
- [ ] Acesso/exportação possui responsável e prazo interno.
- [ ] Correção preserva histórico e motivo da alteração.
- [ ] Anonimização/eliminações revogam links públicos relacionados.
- [ ] Restrições legais ou técnicas à eliminação são explicadas e registradas.
- [ ] Solicitações e respostas são auditadas sem exposição excessiva.

## Retenção e descarte

- [ ] Prazo de retenção está definido por categoria: clientes, avaliações, triagens, auditorias, logs e backups.
- [ ] `SCREENING_RETENTION_DAYS` corresponde à política aprovada.
- [ ] Scheduler de retenção é monitorado.
- [ ] Backups expirados são eliminados com segurança.
- [ ] Restauração reaplica solicitações de anonimização/exclusão posteriores ao ponto restaurado.
- [ ] Mídias e ambientes temporários de suporte são descartados de forma segura.

## Incidentes

- [ ] Plano de resposta possui responsáveis e contatos atualizados.
- [ ] Evidências são preservadas com acesso restrito.
- [ ] Existe avaliação de risco/dano aos titulares.
- [ ] Fluxo de decisão para comunicação à ANPD e titulares está definido.
- [ ] Incidentes geram causa raiz, correção e revisão de controles.

## Evidências mínimas

- Matriz de permissões e revisões periódicas.
- Registros de consentimento/base legal aplicável.
- Auditorias de acesso, alteração, exportação, anonimização e compartilhamento.
- Relatórios de backup e exercícios de restauração.
- Registro de fornecedores e suboperadores.
- Revisões de acessibilidade e segurança por versão.
- Registro de incidentes e ações corretivas.
