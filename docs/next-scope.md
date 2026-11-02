# Próximo escopo recomendado

Projeto: Vita Scan
Revisado em: 2026-11-02

## Contexto atual

O antigo controller central de bioimpedância foi dividido em controllers especializados e no serviço `App\Services\Bioimpedance\BioimpedanceWorkflow`. O frontend possui os fluxos web e PWA, enquanto regras críticas de autorização, validação e persistência permanecem no backend.

## Escopo recomendado

Consolidar a confiabilidade dos fluxos críticos antes de ampliar funcionalidades.

### Prioridades

1. Documentar e revisar a matriz de permissões para administrador, profissional, recepção e visualização.
2. Adicionar cobertura frontend para componentes e hooks críticos quando a estratégia de testes for aprovada.
3. Adicionar testes de fluxo completo para autenticação, cliente, importação, revisão, relatório, compartilhamento, atualização do PWA e perda de conexão.
4. Formalizar procedimentos de backup, restauração, observabilidade e recuperação de desastre.

## Alvos atuais

- `app/Services/Bioimpedance/BioimpedanceWorkflow.php`
- `app/Http/Controllers/Bioimpedance/`
- `resources/js/pwa/`
- `resources/js/pages/DashboardPage.jsx`
- `tests/Feature/BioimpedanceModuleTest.php`
- futura estrutura de testes frontend, somente após decisão explícita sobre dependências

## Restrições

- Não adicionar dependências sem necessidade e autorização dentro da tarefa correspondente.
- Preservar contratos públicos, dados clínicos, auditoria e compatibilidade retroativa.
- Não usar documentos antigos como evidência de que o `BioimpedanceController.php` ainda existe.
- Registrar toda validação conforme `docs/validations.md` e `AGENTS.md`.
