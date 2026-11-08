# Agentes de bioimpedância: personalização e rastreabilidade

## Escopo e versões

Correção de seleção de achados e composição das respostas, sem troca de modelo,
recalcular classificações, alterar OCR ou regravar observações e cuidados aprovados.

- Análise corporal: `rag-client-1.5.0`.
- Cuidados educativos: `care-guidance-2.5.0`.
- Não há migration nem variável de ambiente nova.
- Conteúdo aprovado anteriormente permanece intacto. Para aplicar a nova redação a
  uma avaliação existente, gerar e revisar novamente os dois agentes, nessa ordem.

## Critérios de prioridade

`AssessmentFindingPrioritizer` é compartilhado pelos agentes. Primeiro entram os
achados sinalizados para revisão, preservando sua classificação e origem. Origem
não verificada não é promovida a classificação clínica ou confirmada.

Dentro do mesmo tipo e classificação segmentar, a ordenação usa o afastamento da
faixa registrada; sem faixa, usa o percentual relativo à referência, decrescente
para Alto e crescente para Baixo. Isso é seleção editorial de evidências, não
graduação de risco. Percentuais de gordura e músculo não são comparados entre si.

Nenhuma região é descartada por posição. Regiões equivalentes são agrupadas, todas
as suas evidências são citadas e o maior percentual relativo no grupo de gordura
é identificado. Classificações ausentes continuam não identificadas. Valores
segmentares não são interpretados como percentual local de gordura, força,
função, assimetria clínica ou diagnóstico.

Metadados de faixa adicionados ao contexto não alteram o fingerprint legado da
aprovação. Alterações nos valores medidos, classificação ou perfil continuam
invalidando a aprovação pelas regras existentes.

## Educação e segurança

O motor local combina os achados priorizados, resumo das classificações regionais,
histórico comparável e vínculo à observação aprovada. As possibilidades de
alimentos e movimentos são educativas, sem inferir rotina, restrições, objetivos,
doenças ou capacidade. As lacunas são enviadas também ao provedor externo em
`missing_personal_context`. A observação editada não implica aprovação dos achados
originais; o handoff existente permanece obrigatório.

Continuam obrigatórios revisão profissional, fontes aprovadas, categorias
compatíveis, IDs de evidência disponíveis e limites de tamanho e de histórico.
Não são preenchidos automaticamente IDs inventados ou a flag de revisão ausente.
O guard textual passa a distinguir `tome` de `retome` e uma ressalva explicitamente
negada de uma afirmação diagnóstica. Outras expressões proibidas continuam
bloqueadas, inclusive quando aparecem depois de uma ressalva.

JSON Schema não substitui a validação de significado: a orientação de
[Structured Outputs da OpenAI](https://developers.openai.com/api/docs/guides/structured-outputs)
fundamenta manter a estrutura e as verificações do backend separadas. Não houve
migração de API ou modelo.

## Origem, rejeição e fallback

As requisições continuam registrando `provider`, `attempted_provider`,
`fallback_used`, `fallback_reason`, `prompt_version` e o contexto estruturado.
A interface existente de origem continua distinguindo OpenAI e motor local.

`validation_errors` na requisição descreve o resultado FINAL. Pode estar vazio
após uma resposta externa ter sido rejeitada e o fallback ter passado. Os motivos
externos não devem ser inferidos somente desse campo.

Nas novas saídas, `raw_response.generation_audit` mantém:

- `external_validation_errors` e `final_validation_errors`, separadamente;
- resposta externa estruturada preservada para auditoria restrita;
- modelo tentado, quando aplicável.

O Agente 2 também retorna `blocked_provider_errors`, além de `blocked_claims`, e
`request_id`. Respostas válidas da OpenAI não são substituídas. Se o fallback local
também reprovar, o resultado permanece bloqueado. Nenhuma geração aprova conteúdo
automaticamente.

Esses registros contêm dados sensíveis: não copiar respostas brutas, contexto,
texto clínico, tokens ou credenciais para logs ou ferramentas públicas.

### Consulta somente leitura das avaliações 0002 e 0004

A consulta ao banco LOCAL em 07/11/2026 não encontrou requisições para os IDs 2 e 4.
Isso não demonstra o provedor dos PDFs de produção. É necessário consultar o banco
que efetivamente gerou os documentos, com acesso autorizado. Não mudar o provedor
nem regenerar/aprovar conteúdo apenas para investigar a ocorrência antiga.

Exemplo MySQL, sem conteúdo clínico ou dados de identificação:

```sql
SELECT r.id AS request_id, r.bioimpedance_assessment_id AS assessment_id,
       r.purpose, r.created_at, r.prompt_version, r.model_name,
       r.provider, r.attempted_provider, r.fallback_used, r.fallback_reason,
       r.validation_errors AS final_request_errors,
       JSON_EXTRACT(o.raw_response, '$.generation_audit.external_validation_errors') AS external_errors,
       JSON_EXTRACT(o.raw_response, '$.blocked_provider_errors') AS legacy_external_errors,
       JSON_EXTRACT(o.blocked_claims, '$[*].blocked_reasons') AS blocked_reasons
FROM bioimpedance_ai_analysis_requests r
LEFT JOIN bioimpedance_ai_analysis_outputs o
  ON o.bioimpedance_ai_analysis_request_id = r.id
WHERE r.bioimpedance_assessment_id IN (2, 4)
ORDER BY r.created_at, r.id;
```

Registros antigos não possuem necessariamente todos os detalhes agora gravados.
Não preencher causas retrospectivas por suposição. Conferir também o vínculo da
saída efetivamente aprovada, pois a última tentativa pode não ser a publicada.

## PDF

O acompanhamento genérico é omitido quando já existem cuidados revisados. O
responsável e os limites formam um único fechamento compacto e indivisível. Não
se removem ressalvas nem se reduz a fonte global para forçar seis páginas. Conteúdo
mais extenso pode legitimamente ocupar mais páginas, mas os limites não devem
ficar isolados em uma folha vazia.

## Validação

Testes sem banco e com HTTP simulado (nenhuma chamada paga):

```bash
APP_ENV=testing DB_CONNECTION=sqlite DB_DATABASE=:memory: DB_URL= CACHE_STORE=array SESSION_DRIVER=array QUEUE_CONNECTION=sync php vendor/bin/phpunit tests/Unit/AssessmentFindingsTest.php tests/Unit/AssistantPersonalizationTest.php tests/Unit/CareGuidanceResponseReviewTest.php tests/Unit/ApprovedObservationSnapshotTest.php tests/Unit/ReportGuidancePaginationTest.php --no-progress
```

Os perfis anônimos de regressão reproduzem os pontos numéricos relevantes dos
cenários da auditoria: masculino/atleta e feminino/padrão. A verificação do PDF
usa DomPDF real, os dois perfis, textos novos e históricos, seis páginas e captura
de texto por página para garantir orientações, responsável e limites juntos.

Resultado em 07/11/2026: **23 testes e 184 asserções passaram**, com HTTP simulado.
Quatro PDFs de regressão ficaram com seis páginas, sem folha exclusiva de limites.
Não foi executado build, não houve instalação de dependências nem chamada paga.

A suíte HTTP/persistência `BioimpedanceAssistantHandoffTest` permanece necessária.
A execução local encontrou bloqueio na migration existente
`2026_10_29_000006_secure_screening_data_and_add_retention`: SQLite não suporta
remoção de chave estrangeira pelo nome. O usuário MySQL local não tem permissão
para criar uma base isolada, e a criação/concessão administrativa foi recusada pelo
controle de permissões. Não foi alterada a migration nem usada a base clínica para
testes. Para concluir essa validação, um responsável deve disponibilizar uma base
MySQL exclusiva de testes e autorizar sua utilização. Nunca executar a suíte com
`RefreshDatabase` apontando para uma base clínica ou de produção.

### Roteiro manual

1. Em Clientes, selecionar uma avaliação de cada perfil e abrir Assistente com
   perfil profissional. Gerar análise corporal: conferir tronco, demais regiões,
   classificações originais e uma única conclusão sobre avaliação inicial.
2. Aprovar ou editar a síntese; gerar cuidados. Conferir justificativas diferentes,
   vínculo à síntese aprovada, exemplos educativos e ausência de prescrição.
3. Conferir Origem e, havendo fallback, motivo explícito. Reprovação não pode
   aprovar automaticamente conteúdo. Ausência de revisão e fontes incorretas
   continuam bloqueando a resposta.
4. Aprovar os cuidados, recarregar a avaliação e conferir persistência do conteúdo
   aprovado. Uma alteração real de medição deve exigir nova revisão.
5. Em Relatório, exportar PDF dos dois perfis e de uma avaliação histórica; conferir
   última página sem cortes, assinatura e limites juntos, sem folha só de limites.
6. Com perfil sem permissão clínica e com o dispositivo offline, conferir que não
   seja possível gerar/aprovar conteúdo contornando as restrições existentes.

## Arquivos desta entrega

Em `app/Services/Bioimpedance/`:

- `AssessmentFindingPrioritizer.php` (novo): critérios compartilhados.
- `AssessmentNarrative.php` (novo): síntese e agrupamento sem descarte de regiões.
- `CareGuidanceResponseReview.php` (novo): revisão e fallback rastreável.
- `ClinicalTextGuard.php` (novo): verificação textual compartilhada.
- `AssessmentFindings.php`: metadados, ordenação e classificações ausentes.
- `BioimpedanceAssistantContext.php`: política de seleção e lacunas.
- `BioimpedanceSegmentalClinicalContext.php`: faixas registradas para prioridade.
- `BioimpedanceObservationAssistant.php`: composição, prompt e auditoria.
- `BioimpedanceCareGuidanceAssistant.php`: contexto, prompt, revisão e auditoria.
- `ContextualCareEducation.php`: justificativas e possibilidades educativas.
- `CareGuidanceSemanticValidator.php`: uso do guard, demais barreiras mantidas.

Apresentação: `resources/views/bioimpedance/report-pdf.blade.php`.

Testes e fixtures:

- `tests/Fixtures/BodyCompositionProfiles.php` (novo).
- `tests/Fixtures/BodyReportPresentation.php` (novo).
- `tests/Unit/AssistantPersonalizationTest.php` (novo).
- `tests/Unit/CareGuidanceResponseReviewTest.php` (novo).
- `tests/Unit/ReportGuidancePaginationTest.php` (novo).
- `tests/Unit/AssessmentFindingsTest.php` (fixture ajustada).
- `tests/Feature/BioimpedanceAssistantHandoffTest.php` (auditoria e origem).

Documentação: este arquivo, `docs/assistant-personalization-and-audit.md` (novo).
