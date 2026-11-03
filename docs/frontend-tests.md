# Testes frontend

A suíte frontend protege componentes e regras críticas da área operacional/PWA com Vitest, React Testing Library, `user-event` e jsdom.

## Cobertura inicial

- **Permissões operacionais:** criação de clientes, compartilhamentos, acesso clínico e bloqueio de avaliações por perfil ou estado do cliente.
- **Compartilhamento de relatório:** criação, confirmação de revogação, ausência de permissão e perda de conexão.
- **Protocolo Bodyscan:** presença das confirmações, modo atleta e bloqueio durante processamento.
- **Assistente clínico:** bloqueio sem aprovação, revisão obrigatória, fontes agrupadas e impedimento de aprovação offline.
- **Confirmações sensíveis:** diálogo acessível, confirmação, cancelamento e tecla Escape.
- **Mensagens operacionais:** semântica acessível para erro, sucesso e informações.

## Organização

Os testes ficam próximos aos componentes ou módulos testados, usando o sufixo `.test.js` ou `.test.jsx`. A configuração compartilhada está em `vitest.config.js` e `resources/js/test/setup.js`.

## Execução

Depois de instalar as dependências registradas no `package.json` e atualizar o lockfile:

```bash
npm run test:frontend
```

Durante o desenvolvimento:

```bash
npm run test:frontend:watch
```

O script `scripts/production-check.sh` executa a suíte frontend antes do build. Uma falha deve interromper a preparação para produção.

## Diretrizes para novos testes

1. Testar o comportamento observado pelo usuário, evitando detalhes internos do componente.
2. Preferir consultas por papel, rótulo e nome acessível.
3. Simular API apenas na fronteira do serviço; não reproduzir regras do backend dentro do teste.
4. Cobrir pelo menos sucesso, erro/permissão e estado offline quando aplicável.
5. Não incluir dados pessoais ou clínicos reais em fixtures.
