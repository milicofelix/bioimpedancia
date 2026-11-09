# Relatório de consumo da IA

## Escopo

O painel administrativo (`/dashboard`, área de configurações) apresenta **Consumo e custos da IA**, com filtros de data e operação, paginação e total estimado em USD. A API autenticada `GET /bioimpedance/ai-usage?from=2026-11-01&to=2026-11-08` aceita somente administradores ativos, inclusive quando os módulos clínicos estão desativados. O intervalo máximo é de 366 dias.

Cada chamada HTTP à OpenAI gera um registro separado: OCR Bodyscan, segunda leitura segmentar, OCR Relaxmedic, análise corporal e próximos cuidados. Não há cobrança fixa por avaliação: uma avaliação pode envolver várias chamadas. Gerar novamente uma sugestão pode consumir novamente. Respostas depois bloqueadas pela revisão ou substituídas pelo motor local continuam representando uma chamada externa; a revisão clínica não altera o consumo já realizado. O motor local, sozinho, não gera registro de chamada paga.

Este recurso não consulta saldo, não faz recargas e não requer chave administrativa de faturamento. Não captura uso de outros projetos, mesmo que compartilhem a chave. Não reconstitui chamadas antigas. O histórico começa após a implantação.

## Cálculo e limitações

Fontes oficiais consultadas em 08/11/2026:

- [Preços](https://developers.openai.com/api/docs/pricing).
- [Contabilização de cache](https://developers.openai.com/api/docs/guides/prompt-caching).

Os preços por milhão de tokens ficam em `config/ai_usage.php`. Inicialmente estão cadastrados Terra e GPT-5.1 em modalidade padrão e seus snapshots datados. Para outro modelo ou modalidade, cadastre e revise os preços antes de confiar nos totais. Modalidades não cadastradas não recebem custo zero. Atualize `price_version` ao alterar preços; cada registro guarda sua própria tabela de preços, preservando o histórico.

O cálculo soma entrada comum, leitura de cache, escrita de cache por duração e saída, com tarifas distintas. Escritas e leituras já estão incluídas na contagem de entrada e não são somadas novamente como tokens. Tokens de raciocínio já integram a saída. Imagens participam dos tokens informados na resposta, sem taxa de imagem inventada. Terra considera a tarifa de contexto longo quando aplicável.

Valores são **estimativas**, não faturas. Descontos contratuais, impostos, câmbio do cartão, modalidades/preços não cadastrados e outros encargos não são incluídos. O custo em USD é preservado com 12 casas decimais; a apresentação usa 6–8 para não arredondar pequenas chamadas a centavos inteiros.

Falhas HTTP são registradas antes de lançar a exceção para o fluxo original. Respostas incompletas ainda podem ter consumo. Sem `usage` válido, com cache inconsistente, preço desconhecido ou erro de conexão, o custo permanece nulo e o total é identificado como parcial — nunca gratuito. “Resposta recebida” indica transporte HTTP, não aprovação clínica da resposta.

Falhas de gravação não repetem a chamada paga nem interrompem o atendimento: emitem apenas o evento sanitizado `ai_usage_recording_failed`, contendo a operação, nos logs. Monitore esse evento. Uma falha de persistência, encerramento abrupto do processo ou indisponibilidade do banco pode causar lacunas; este relatório não substitui a conciliação com o painel de faturamento da OpenAI.

## Privacidade

O registro contém somente operação, modelo, modalidade, estado HTTP, duração, contagens, estimativa e versão dos preços. Não contém cliente, usuário, nome de arquivo, imagem, prompt, resposta clínica, chave ou cabeçalhos. Endpoint e painel são exclusivos do administrador. A resposta usa `Cache-Control: private, no-store`; não salvar este histórico no armazenamento do navegador. Trate o histórico como registro financeiro interno, inclua-o nos backups existentes e defina a retenção administrativa antes de qualquer futura rotina de descarte. Esta entrega não exclui registros automaticamente.

## Implantação

1. Publicar o código e aplicar a nova migration pelo processo habitual: `php artisan migrate --force`. Não executar `migrate:fresh`.
2. Publicar os assets atualizados pelo pipeline habitual. Limpar/regerar caches de configuração e rotas conforme o procedimento do ambiente.
3. Opcionalmente configurar `AI_USAGE_USD_BRL_RATE` (número com ponto decimal) e `AI_USAGE_EXCHANGE_RATE_DATE` (AAAA-MM-DD). É uma referência manual, não cotação atual nem valor debitado em reais. Atualize ambas juntas. Sem ambas, só USD é exibido.
4. Conferir o preço do modelo usado em `OPENAI_MODEL` e `OPENAI_VISION_MODEL` com a tabela oficial. Não mudar modelo, prompts ou limites apenas para habilitar contabilização.

Nenhuma nova dependência ou credencial é necessária.

## Roteiro manual

1. Acessar `/dashboard` como administrador. Localizar **Consumo e custos da IA**. Antes da primeira chamada, esperar estado vazio.
2. Em homologação e com autorização para o consumo, importar uma imagem Bodyscan. Voltar ao painel, selecionar o período e atualizar: esperar registros distintos de OCR inicial e segunda leitura, com modelo, tokens e estimativas ou motivo de indisponibilidade.
3. Gerar cada uma das duas sugestões. Atualizar: esperar uma chamada externa por geração efetivamente enviada; gerar com motor local deve deixar a contagem inalterada. Uma resposta externa bloqueada não deve desaparecer do histórico.
4. Recarregar a página, filtrar cada operação e navegar entre páginas: esperar persistência e totais referentes a todo o intervalo filtrado, não somente à página atual. Conferir câmbio de referência apenas quando configurado e datado.
5. Em homologação, simular indisponibilidade do provedor ou credenciais de teste inválidas. Esperar tratamento de erro/fallback existente e registro com custo desconhecido quando não houver tokens informados. Não invalidar a chave de produção para esta conferência.
6. Acessar com profissional, recepção e visualizador: painel ausente; tentar a URL de consulta autenticada deve retornar 403. Sem autenticação, seguir o redirecionamento de login existente. Desconectar a rede na consulta administrativa deve apresentar erro recuperável ao atualizar.
7. Conferir na aba Network e no banco que nenhum conteúdo clínico ou credencial faz parte dos registros retornados.

Testes automatizados e build não executados por orientação do `AGENTS.md`. Os testes adicionados são especificações automatizadas pendentes de execução no processo autorizado de validação.
