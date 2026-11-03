# Testes end-to-end com Playwright

A suíte E2E valida as jornadas críticas no navegador: login, logout, navegação PWA, avaliação, OCR, relatório, compartilhamento e perda de conexão.

## Estratégia

- Login e sessão usam o backend real configurado para o ambiente de teste.
- A área operacional usa dados exclusivamente sintéticos.
- OCR e persistência de avaliação são interceptados no navegador para tornar o cenário determinístico e impedir envio de imagem clínica real.
- Links, PDF e compartilhamento usam respostas sintéticas e não enviam mensagens externas.
- Vídeo, screenshot e trace são preservados somente quando houver falha.

## Preparação

1. Instale as dependências declaradas no `package.json` e os navegadores do Playwright.
2. Mantenha a aplicação disponível em um ambiente isolado.
3. Exporte as variáveis do arquivo `.env.e2e.example` com um usuário profissional exclusivo para automação.

```bash
npx playwright install chromium
export PLAYWRIGHT_BASE_URL=http://localhost:8081
export PLAYWRIGHT_USER_EMAIL=profissional@vita-scan.local
export PLAYWRIGHT_USER_PASSWORD='senha-do-ambiente-de-teste'
npm run test:e2e
```

Nunca use credenciais de produção. O arquivo de sessão gerado fica em `playwright/.auth` e não deve ser versionado.

## Cenários cobertos

- Login válido, login inválido e logout.
- Navegação inferior, hash, histórico e botão Voltar.
- Perda e retorno da conexão.
- Seleção de JPEG, retorno do OCR, conferência humana e criação da avaliação.
- Erro de OCR sem criação indevida de avaliação.
- Abertura do relatório e disponibilidade do PDF.
- Criação de link temporário e revogação com confirmação.

## Execução visual

Para acompanhar o navegador:

```bash
npm run test:e2e:headed
```

Artefatos de falha são gravados em `test-results` e o relatório HTML em `playwright-report`; ambos estão ignorados pelo Git.
