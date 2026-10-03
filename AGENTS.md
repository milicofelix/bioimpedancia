# Instruções para agentes

Estas instruções se aplicam a todo o repositório.

## Testes e build

- Não execute testes automatizados.
- Não execute comandos de build.
- Não instale ou atualize dependências apenas para testar ou gerar o build.
- Informe claramente na entrega que testes automatizados e build não foram executados por orientação deste arquivo.

## Validação manual

- Ao concluir qualquer alteração, inclua na resposta final um passo a passo de testes manuais no navegador.
- O roteiro deve ser específico para a funcionalidade alterada e conter:
  1. Como acessar a tela afetada.
  2. Quais ações executar.
  3. Qual resultado esperar em cada etapa.
  4. Ao menos uma verificação de persistência, recarregando a página quando aplicável.
  5. Verificações de erro, permissão ou estado alternativo quando forem relevantes.

## Sugestão de commit

- Ao concluir uma alteração, sempre sugira uma mensagem de commit no formato:

```bash
git c "feat: msg"
```

- Substitua `feat` pelo tipo Conventional Commit mais adequado quando necessário, como `fix`, `refactor`, `docs`, `test` ou `chore`.
- Substitua `msg` por uma descrição curta, objetiva e em português sobre a alteração realizada.
- Apenas sugira o comando; não crie o commit sem solicitação explícita do usuário.
