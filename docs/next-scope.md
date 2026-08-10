# Proximo Escopo Recomendado

Projeto: Ricostyemagrecimento
Data: 2026-08-09
Fonte: ABS Engineering OS / revisao manual Codex

## Como Usar

Este arquivo registra o escopo aceito para orientar o proximo pacote em modo Execucao.
Ao escanear o projeto novamente, o gerador de pacotes deve tratar este arquivo como fonte primaria para montar uma missao pequena, reversivel e coerente com o estado atual do projeto.

## Escopo Aceito

Reduzir risco de manutencao em `app/Http/Controllers/Bioimpedance/BioimpedanceController.php` com um incremento pequeno e reversivel.

## Objetivo De Execucao

Adicionar ou ajustar cobertura de caracterizacao e, somente se seguro, extrair uma responsabilidade isolada do controller sem alterar comportamento publico.

## Alvos Provaveis

- `app/Http/Controllers/Bioimpedance/BioimpedanceController.php`
- `tests/Feature/BioimpedanceModuleTest.php`
- Servico novo em `app/Services/Bioimpedance/`, apenas se a extracao ficar pequena e clara.

## Fora Do Escopo

- Migrations, seeds ou alteracoes de banco.
- Mudancas de frontend.
- Dependencias novas.
- Alteracoes de deploy, Docker ou CI.
- Mudancas no contrato publico das rotas ou payloads.

## Criterios De Aceite

- Patch pequeno e revisavel.
- Comportamento publico preservado.
- Teste de caracterizacao cobrindo o comportamento extraido ou protegido.
- Validacoes registradas em `docs/validations.md` ou na resposta final.
- Rollback simples por revert do commit.

## Proximo Pacote Sugerido

Modo: Execucao

Objetivo: Reduzir risco de manutencao em `app/Http/Controllers/Bioimpedance/BioimpedanceController.php` com um incremento pequeno e reversivel: primeiro adicionar ou ajustar cobertura de caracterizacao e, somente se seguro, extrair uma responsabilidade isolada sem alterar comportamento publico.

Branch sugerida:

```bash
git switch -c codex/reduce-bioimpedance-controller-risk
```

Commit sugerido:

```bash
git c "refactor: reduce bioimpedance controller responsibility"
```

## Observacoes De Automacao

- Projeto alvo: Ricostyemagrecimento.
- Arquivos listados como alvos sao hipoteses de investigacao, nao obrigacao de edicao.
- Se o scanner sugerir outro produto ou outro projeto, marcar como contexto incorreto antes de executar.
- Se o patch passar de 15 arquivos, interromper e justificar antes de continuar.
