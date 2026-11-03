# Próximo escopo recomendado

Projeto: Vita Scan
Revisado em: 2026-11-02

## Contexto atual

O antigo controller central de bioimpedância foi dividido em controllers especializados e no serviço `App\Services\Bioimpedance\BioimpedanceWorkflow`. O frontend possui os fluxos web e PWA, enquanto regras críticas de autorização, validação e persistência permanecem no backend.

## Escopo recomendado

Consolidar a confiabilidade dos fluxos críticos antes de ampliar funcionalidades.

### Entregas formalizadas

1. Matriz de permissões para administrador, profissional, recepção e visualização.
2. Cobertura frontend dos componentes e hooks críticos.
3. Fluxos completos de autenticação, cliente, importação, revisão, relatório, compartilhamento, atualização do PWA e perda de conexão.
4. Procedimentos de backup, restauração, observabilidade, recuperação de desastre, acessibilidade e checklist LGPD.

As rotinas operacionais exigem validação periódica em ambiente controlado. A existência dos scripts e checklists não substitui exercício de restauração, revisão jurídica ou auditoria de acessibilidade.

## Alvos atuais

- `app/Services/Bioimpedance/BioimpedanceWorkflow.php`
- `app/Http/Controllers/Bioimpedance/`
- `resources/js/pwa/`
- `resources/js/pages/DashboardPage.jsx`
- `tests/Feature/BioimpedanceModuleTest.php`
- `docs/operations/`
- `docs/accessibility-checklist.md`
- `docs/lgpd-checklist.md`

## Restrições

- Não adicionar dependências sem necessidade e autorização dentro da tarefa correspondente.
- Preservar contratos públicos, dados clínicos, auditoria e compatibilidade retroativa.
- Não usar documentos antigos como evidência de que o `BioimpedanceController.php` ainda existe.
- Registrar toda validação conforme `docs/validations.md` e `AGENTS.md`.
