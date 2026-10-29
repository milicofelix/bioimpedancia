# Instruções para agentes

Estas instruções se aplicam a todo o repositório e a qualquer tipo de tarefa, incluindo `feature`, `fix`, `refactor`, `docs`, `chore` e alterações emergenciais.

## Execução de fases planejadas

- Quando o usuário solicitar a execução de uma fase numerada, implemente todos os itens listados para essa fase antes de declará-la concluída.
- Não marque uma fase como concluída se algum item estiver parcial, apenas preparado, simulado ou deixado para uma etapa futura.
- Se um bloqueio externo impedir a conclusão integral, informe claramente quais itens foram concluídos, quais continuam bloqueados e o que é necessário para prosseguir.
- Não altere por conta própria o escopo ou a ordem das fases combinadas. Sugestões de reorganização devem ser apresentadas ao usuário sem substituir a execução solicitada.

## Testes e build

- Não execute testes automatizados.
- Não execute testes unitários, de integração, end-to-end, linters ou verificações que iniciem suítes de testes.
- Não execute comandos de build.
- Não execute builds de produção, compilação de assets ou comandos equivalentes apenas para validar uma alteração.
- Não instale ou atualize dependências apenas para testar ou gerar o build.
- Informe claramente na entrega que testes automatizados e build não foram executados por orientação deste arquivo.

## Boas práticas de desenvolvimento

- Toda nova implementação deve seguir princípios de código limpo, SOLID, baixo acoplamento, alta coesão e responsabilidade única.
- Evite ampliar arquivos ou classes que já concentram muitas responsabilidades. Antes de adicionar comportamento relevante, avalie extrair componentes, hooks, services, actions, policies, form requests ou classes especializadas.
- Não crie abstrações apenas por antecipação. Use design patterns quando resolverem um problema concreto e tornarem o fluxo mais simples de entender, testar e manter.
- Prefira composição a herança e dependências explícitas a acessos globais ou estados implícitos.
- Mantenha regras de negócio e validações críticas no backend. A validação no frontend deve melhorar a experiência, mas nunca ser a única proteção.
- Controllers devem coordenar o fluxo e permanecer enxutos. Regras de negócio, integrações, processamento e persistência complexa devem ficar em serviços ou actions apropriados.
- Use Form Requests para validações extensas e Policies ou Gates para autorização quando a funcionalidade justificar essa separação.
- No React, mantenha páginas como composição de componentes. Extraia componentes, hooks e utilitários quando uma tela passar a acumular formulários, requisições, regras e estados independentes.
- Evite duplicação de regras, constantes, rótulos e transformações. Defina uma fonte de verdade adequada para cada informação.
- Preserve compatibilidade com dados e fluxos existentes, salvo quando o usuário solicitar explicitamente uma quebra de comportamento.
- Não altere código fora do escopo da tarefa sem necessidade concreta.
- Não sobrescreva nem descarte alterações existentes do usuário.

## Banco de dados e persistência

- Mudanças de estrutura devem ser feitas por migrations reversíveis e não destrutivas sempre que possível.
- Não edite migrations que possam já ter sido executadas; crie uma nova migration para evoluir o banco.
- Operações que gravem um registro principal e sua auditoria devem ser atômicas, usando transação quando necessário.
- Evite exclusão física de dados clínicos ou auditáveis sem solicitação explícita e análise do impacto.
- Preserve rastreabilidade de criação, correção, cancelamento e compartilhamento de dados sensíveis.

## Segurança e privacidade

- Trate dados clínicos e pessoais como sensíveis.
- Aplique autorização também no backend; ocultar ou desabilitar controles no frontend não é suficiente.
- Valide uploads por extensão, MIME, conteúdo real, tamanho e demais restrições aplicáveis.
- Não registre senhas, tokens, imagens clínicas, dados pessoais ou segredos em logs e mensagens de erro.
- Não exponha detalhes internos de exceções para o usuário final.
- Considere LGPD, minimização de dados, tempo de retenção e auditoria em novas funcionalidades.
- Não adicione chaves, credenciais ou informações de ambiente ao repositório.

## Dependências e integrações

- Reutilize recursos já disponíveis no projeto antes de adicionar uma nova dependência.
- Não instale, remova ou atualize pacotes sem necessidade direta para a tarefa.
- Integrações externas devem ter tratamento de timeout, indisponibilidade, resposta inválida e mensagens compreensíveis para o usuário.
- Falhas externas não devem deixar registros incompletos ou inconsistentes.

## Experiência e acessibilidade

- Preserve os fluxos existentes em desktop e dispositivos móveis.
- Novas interfaces devem considerar estados de carregamento, vazio, erro, permissão insuficiente e conexão indisponível.
- Campos devem possuir rótulos claros, mensagens de validação próximas e navegação por teclado quando aplicável.
- Ações destrutivas ou irreversíveis devem exigir confirmação e explicar o impacto.

## Validação manual

- Ao concluir qualquer alteração, inclua na resposta final um passo a passo de testes manuais no navegador.
- O roteiro deve ser específico para a funcionalidade alterada e conter:
  1. Como acessar a tela afetada.
  2. Quais ações executar.
  3. Qual resultado esperar em cada etapa.
  4. Ao menos uma verificação de persistência, recarregando a página quando aplicável.
  5. Verificações de erro, permissão ou estado alternativo quando forem relevantes.
- Quando não houver alteração de interface, descreva uma validação manual equivalente pela funcionalidade afetada, sem executar testes automatizados.

## Revisão antes da entrega

- Revise apenas por inspeção estática, respeitando a proibição de testes e build.
- Confira o diff para evitar arquivos acidentais, credenciais, alterações fora do escopo ou código temporário de depuração.
- Informe migrations, variáveis de ambiente ou ações manuais necessárias para ativar a funcionalidade.
- Resuma os arquivos e comportamentos alterados sem afirmar que algo foi testado quando não foi.

## Sugestão de commit

- Ao concluir uma alteração, sempre sugira uma mensagem de commit no formato:

```bash
git c "feat: msg"
```

- Substitua `feat` pelo tipo Conventional Commit mais adequado quando necessário, como `fix`, `refactor`, `docs`, `test` ou `chore`.
- Substitua `msg` por uma descrição curta, objetiva e em português sobre a alteração realizada.
- Apenas sugira o comando; não crie o commit sem solicitação explícita do usuário.
- Sugira apenas um commit principal por entrega, salvo quando o usuário solicitar a separação em múltiplos commits.
