# Comparação controlada com GPT-6.1 Terra

## Configuração

O OCR principal, a segunda leitura segmentar e os dois agentes continuam usando
a API Responses, os schemas estruturados e as barreiras de segurança existentes.
Não há nova integração nem mudança de chave obrigatória.

No `.env` do ambiente de comparação:

```dotenv
OPENAI_MODEL=gpt-6.1-terra
OPENAI_VISION_MODEL=gpt-6.1-terra
OPENAI_REASONING_EFFORT=medium
OPENAI_VISION_REASONING_EFFORT=medium
OPENAI_ASSISTANT_MAX_OUTPUT_TOKENS=6000
BIOIMPEDANCE_ASSISTANT_PROVIDER=hybrid
```

`medium` foi escolhido explicitamente para OCR e agentes, alinhado ao padrão do
Terra. Não equivale ao mesmo custo ou latência de uma sessão no Codex. Para comparar
economia, use `low` ou `none` somente no fluxo desejado. O esforço anterior efetivo
do GPT-5.1 era `none`; essa mudança pode elevar uso de tokens e tempo de resposta.
Os timeouts e limites de saída existentes foram mantidos. Se houver resposta
incompleta ou timeout, registre o caso antes de ajustar esforço ou limites; não
considere uma resposta do motor local como uma comparação bem-sucedida do Terra.
O limite de saída dos agentes inclui raciocínio e texto visível; não limita entrada,
imagens, número de chamadas ou gasto mensal. Os limites do OCR foram preservados:
Bodyscan 9000, segunda leitura 4200 e Relaxmedic 3000 tokens por chamada.

Após editar o ambiente, execute no diretório do projeto:

```bash
docker compose exec -T app php artisan config:clear
```

Em instalação sem Docker, use `php artisan config:clear`. Se a implantação usa
configuração em cache, reaplique `php artisan config:cache` após definir o ambiente.
Reinicie workers persistentes caso sua instalação os utilize. Não é necessário
executar migrations ou build de frontend para esta mudança.

O `.env` local foi ajustado sem alterar `OPENAI_API_KEY`. O arquivo é ignorado pelo
Git; para produção, configure as mesmas variáveis no servidor. Alterar apenas
`.env.example` não modifica uma instalação existente.

## Painel da OpenAI

A tela de API keys não escolhe o modelo. O modelo é enviado pelo Vita Scan em cada
requisição. A chave ativa `bioimpedancia`, no projeto `Default` da captura de tela,
pode ser reutilizada, desde que o projeto tenha acesso ao modelo.

1. Confira o projeto associado à chave, sem revogá-la ou criar outra apenas pela troca.
2. Se a chave for restrita, confira a permissão de criação na API Responses (`/v1/responses`).
3. Confira acesso ao modelo e limites do projeto nas configurações disponíveis à sua conta.
4. Confira saldo/faturamento e acompanhe Usage, filtrando por projeto, modelo e período.
5. Não confunda configuração do aplicativo ChatGPT com configuração da API.

Disponibilidade efetiva depende da organização e de suas permissões; não foi
realizada uma chamada para confirmar acesso. Se a API recusar o modelo, não
remova validações para contornar a recusa: confira acesso/permissões ou faça rollback.

## Roteiro manual

Faça a comparação em homologação, com imagens anonimizadas e dados de demonstração.
Cada processamento e cada nova sugestão podem consumir créditos, inclusive a segunda
leitura do OCR. Nenhuma chamada paga foi executada durante esta implementação.

1. Em Avaliar, processe uma imagem representativa de cada layout utilizado. Confira
   valores, unidades, lados, tronco e gordura subcutânea com o original. A nova
   extração deve registrar `processor_model=gpt-6.1-terra`.
2. Complete a conferência e crie uma avaliação de teste. Abra o Assistente, gere a
   análise corporal e revise antes de aprovar. Depois gere os próximos cuidados.
3. Confira a origem real de cada resposta: `provider=openai` e modelo Terra quando
   houver sucesso externo. `local_reference_engine` indica motor local e não deve
   ser contabilizado como resultado do Terra; registre também a razão do fallback.
4. Após aprovar, recarregue e reabra a avaliação. Textos aprovados, dados e autoria
   devem permanecer. Relatórios antigos não são reescritos pela mudança de modelo.
5. Simule conexão indisponível no navegador antes de novo envio. Deve haver aviso,
   sem avaliação incompleta salva. Use também uma imagem sem relatório: ela não deve
   ser aceita apenas porque o modelo mudou. Permissões clínicas permanecem exigidas.
6. Compare no painel Usage os tokens e custos com o período/modelo anterior. Registre
   duração, erros, correções manuais e fallback. Compare custo por avaliação concluída,
   não apenas tokens por requisição. Use uma amostra pequena antes de ampliar.

Sugestões já existentes não se transformam em respostas Terra automaticamente; gere
uma nova sugestão conscientemente no ambiente de teste para avaliar o modelo.

## Rollback

Para voltar integralmente ao modelo anterior:

```dotenv
OPENAI_MODEL=gpt-5.1
OPENAI_VISION_MODEL=gpt-5.1
OPENAI_REASONING_EFFORT=none
OPENAI_VISION_REASONING_EFFORT=none
```

Recarregue a configuração conforme explicado acima. Para comparar OCR e agentes
separadamente, altere somente `OPENAI_VISION_MODEL` ou somente `OPENAI_MODEL`.
Não apague avaliações, aprovações ou auditorias para trocar de modelo.

## Referências

- [Modelo Terra](https://developers.openai.com/api/docs/models/gpt-6.1-terra)
- [Compatibilidade da migração](https://developers.openai.com/api/docs/guides/latest-model/gpt-6-astra.md#migration-quickstart)
- [Permissões da API](https://developers.openai.com/api/docs/guides/rbac#permissions)
- [Usage](https://platform.openai.com/usage)

Revisão desta entrega: inspeção estática. Testes automatizados, build e chamadas
à API não foram executados, conforme `AGENTS.md`.
