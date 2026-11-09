# Relatório de consumo da IA

## Escopo

O painel administrativo (`/dashboard`, área de configurações) apresenta **Consumo e custos da IA**, com filtros de data e operação, paginação e total estimado em USD. A API autenticada `GET /bioimpedance/ai-usage?from=2026-11-01&to=2026-11-08` aceita somente administradores ativos, inclusive quando os módulos clínicos estão desativados. O intervalo máximo é de 366 dias.

Cada chamada HTTP à OpenAI gera um registro separado: OCR Bodyscan, segunda leitura segmentar, OCR Relaxmedic, análise corporal e próximos cuidados. Não há cobrança fixa por avaliação: uma avaliação pode envolver várias chamadas. Gerar novamente uma sugestão pode consumir novamente. Respostas depois bloqueadas pela revisão ou substituídas pelo motor local continuam representando uma chamada externa; a revisão clínica não altera o consumo já realizado. O motor local, sozinho, não gera registro de chamada paga.

Este recurso não consulta saldo, não faz recargas e não requer chave administrativa de faturamento. Não captura uso de outros projetos, mesmo que compartilhem a chave. Não reconstitui chamadas antigas. O histórico começa após a implantação.

## Consumo histórico inicial de US$ 1,50

O seeder **opcional** `AiUsageHistoricalOpeningSeeder` registra somente os **US$ 1,50 confirmados pelo administrador** como consumo histórico consolidado. Não utiliza os US$ 2,12 de gasto mensal nem os soma ao valor confirmado. Não registra recarga de US$ 10,00, saldo de US$ 8,50 ou qualquer saldo atual.

Para incluir esse lançamento, após publicar o código:

```bash
php artisan migrate --force
php artisan db:seed --class=AiUsageHistoricalOpeningSeeder --force
```

Não chamar este seed automaticamente pelo `DatabaseSeeder`: é um lançamento específico deste histórico, não um dado demo ou padrão de novas instalações. A migration adicional cria uma referência de importação única e anulável, sem alterar registros existentes. `firstOrCreate` e a restrição única impedem duplicação, inclusive em execuções concorrentes. Reexecutar o seed não altera o valor nem a data já registrados. Não executar `migrate:fresh` para esta operação.

O registro usa a data da **inclusão**, pois não foi informado um período histórico exato. O painel explica essa limitação; seus filtros incluem o lançamento pela data de inclusão, sem distribuir valores por dias inventados. Tokens, modelo real, quantidade de requisições, modalidade, preço por token e estado HTTP não são reconstruídos. `model=not_recorded` é apenas um marcador técnico; `duration_ms=0` preenche o campo obrigatório do esquema anterior, não representa uma medição de tempo. Esses dois campos não são apresentados como dados de uma chamada. `price_snapshot` permanece nulo. A coluna legada `estimated_cost_usd` armazena o valor consolidado, distinguido por `cost_status=historical_reported` e `operation=historical_opening`.

O total passa a apresentar **histórico informado + estimativas de chamadas novas**. O histórico aparece separado, entra uma única vez na soma e não aumenta a contagem de requisições, tokens ou chamadas sem custo calculável. Chamadas já existentes não são modificadas. Como não há detalhamento antigo nem corte temporal confirmado, não há conciliação automática de eventuais sobreposições: confirme que o valor inicial corresponde ao consumo anterior às chamadas já registradas antes de executar o seed. O sistema não subtrai valores por suposição.

Validação manual específica:

1. Executar a migration e o seed acima no ambiente desejado. Como administrador, acessar `/dashboard` → **Consumo e custos da IA** e selecionar um intervalo que inclua o dia da execução.
2. Filtrar por **Consumo histórico consolidado**: esperar um único lançamento de US$ 1,50, aviso de valor informado, zero requisições e ausência de tokens/modelo/tempo fictícios.
3. Reexecutar somente o seed e recarregar a página: esperar o mesmo lançamento, valor e data, sem duplicação.
4. Selecionar **Todas**: esperar US$ 1,50 somados aos custos conhecidos das chamadas existentes, com contagem de chamadas inalterada. Filtrar uma operação de OCR ou agente deve excluir o histórico daquele subtotal. Um intervalo anterior à inclusão não deve exibir o lançamento.
5. Acessar como profissional, recepção ou visualizador: o painel deve continuar indisponível; a consulta direta ao endpoint deve continuar retornando 403.

## Cálculo e limitações

Fontes oficiais consultadas em 08/11/2026:

- [Preços](https://developers.openai.com/api/docs/pricing).
- [Contabilização de cache](https://developers.openai.com/api/docs/guides/prompt-caching).

Os preços por milhão de tokens ficam em `config/ai_usage.php`. Inicialmente estão cadastrados Terra e GPT-5.1 em modalidade padrão e seus snapshots datados. Para outro modelo ou modalidade, cadastre e revise os preços antes de confiar nos totais. Modalidades não cadastradas não recebem custo zero. Atualize `price_version` ao alterar preços; cada registro guarda sua própria tabela de preços, preservando o histórico.

O cálculo soma entrada comum, leitura de cache, escrita de cache por duração e saída, com tarifas distintas. Escritas e leituras já estão incluídas na contagem de entrada e não são somadas novamente como tokens. Tokens de raciocínio já integram a saída. Imagens participam dos tokens informados na resposta, sem taxa de imagem inventada. Terra considera a tarifa de contexto longo quando aplicável.

Valores são **estimativas**, não faturas. Descontos contratuais, impostos, câmbio do cartão, modalidades/preços não cadastrados e outros encargos não são incluídos. O custo em USD é preservado com 12 casas decimais. A apresentação usa o padrão brasileiro com duas casas (`US$ 1,50`, `R$ 7,50`). Valores positivos inferiores a um centavo preservam até oito casas, sem zeros finais desnecessários, para não apresentar consumo pequeno como gratuito. Valores menores que a precisão exibida aparecem como “Menos de …”. A cotação mantém até oito casas decimais quando necessário; o cálculo utiliza a taxa configurada, sem arredondá-la previamente.

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

### Cotação não é recarga

`AI_USAGE_USD_BRL_RATE=10.00` significa **US$ 1 = R$ 10**, e não “comprei US$ 10 de créditos”. Por isso, nessa configuração, US$ 1,50 são convertidos matematicamente para R$ 15,00. O total da recarga não permite descobrir a taxa de câmbio.

Se a referência desejada for o custo efetivo da compra, use `valor pago em reais / créditos comprados em dólares`, deixando claro que essa referência pode incorporar taxas da compra. **Exemplo fictício, não uma cotação atual:** US$ 10 custaram R$ 50 → configurar `AI_USAGE_USD_BRL_RATE=5.00`, com a data da compra em `AI_USAGE_EXCHANGE_RATE_DATE`. Nesse exemplo, US$ 1,50 equivalem a R$ 7,50. Para representar uma cotação de mercado, use a taxa e data consultadas na fonte escolhida. Não substitua a taxa pela cotação de outro dia sem intenção explícita.

Após editar o `.env`, atualizar o cache de configuração pelo procedimento do ambiente (por exemplo, `php artisan config:clear`) e recarregar o painel. A alteração de apresentação também requer publicar os assets pelo pipeline habitual. Não altera custos originais em USD nem o seed histórico.

Conferência manual: no painel administrativo de consumo, confirmar `US$ 1,50` no histórico, o equivalente em `R$` no resumo e nos lançamentos quando houver cotação válida, além da taxa em linha separada. Recarregar para conferir persistência. Sem taxa e data configuradas, esperar apenas USD com instrução de configuração — nunca um valor em reais inventado. Conferir valores menores que US$ 0,01 sem arredondá-los para zero e conferir a quebra de texto em celular estreito.

## Roteiro manual

1. Acessar `/dashboard` como administrador. Localizar **Consumo e custos da IA**. Antes da primeira chamada, esperar estado vazio.
2. Em homologação e com autorização para o consumo, importar uma imagem Bodyscan. Voltar ao painel, selecionar o período e atualizar: esperar registros distintos de OCR inicial e segunda leitura, com modelo, tokens e estimativas ou motivo de indisponibilidade.
3. Gerar cada uma das duas sugestões. Atualizar: esperar uma chamada externa por geração efetivamente enviada; gerar com motor local deve deixar a contagem inalterada. Uma resposta externa bloqueada não deve desaparecer do histórico.
4. Recarregar a página, filtrar cada operação e navegar entre páginas: esperar persistência e totais referentes a todo o intervalo filtrado, não somente à página atual. Conferir câmbio de referência apenas quando configurado e datado.
5. Em homologação, simular indisponibilidade do provedor ou credenciais de teste inválidas. Esperar tratamento de erro/fallback existente e registro com custo desconhecido quando não houver tokens informados. Não invalidar a chave de produção para esta conferência.
6. Acessar com profissional, recepção e visualizador: painel ausente; tentar a URL de consulta autenticada deve retornar 403. Sem autenticação, seguir o redirecionamento de login existente. Desconectar a rede na consulta administrativa deve apresentar erro recuperável ao atualizar.
7. Conferir na aba Network e no banco que nenhum conteúdo clínico ou credencial faz parte dos registros retornados.

Testes automatizados e build não executados por orientação do `AGENTS.md`. Os testes adicionados são especificações automatizadas pendentes de execução no processo autorizado de validação.
