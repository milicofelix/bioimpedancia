# Observações e próximos cuidados

## Contrato e limites

- `BioimpedanceAssistantContext` consolida perfil, métricas, índices, segmentos, impedâncias e histórico. `AssessmentFindings` produz fatos com IDs de evidência, origem da classificação, prioridade de conferência e limitações. Prioridade não representa urgência clínica.
- Valores ausentes não são convertidos em zero. Pontuação corporal não entra na interpretação. Classificações calculadas pelo sistema, impressas no relatório, derivadas da faixa de referência e alteradas na revisão têm origens distintas. Registros antigos sem procedência verificável não viram novos pontos de atenção automaticamente.
- O primeiro agente persiste os achados estruturados junto à sugestão. A interface permite conferir esses dados e suas limitações antes da aprovação.
- `ApprovedObservationContext` exige a última observação aprovada, com texto idêntico ao registro atual e, nas novas gerações, impressão digital dos dados compatível. O segundo agente recebe ID da saída, autor, data, hash do texto e achados da versão aprovada. Texto editado não implica aprovação automática dos achados originais.
- `AssistantApprovalGuard` verifica novamente o vínculo dentro da transação de aprovação, com bloqueio da avaliação. Fontes, texto aprovado e auditoria mantêm o fluxo transacional existente.

## Orientações e fallback

O motor local é determinístico, não uma segunda IA nem uma interpretação clínica completa. `ContextualCareEducation` seleciona orientações educativas segundo achados disponíveis, idade registrada e possibilidade de comparação do histórico. Ausência de hábitos alimentares, atividade, força e função é explicitada; não se presume sedentarismo nem se prescrevem quantidades, dieta ou treino. Há linguagem compartilhada de segurança intencionalmente repetida.

Cada orientação identifica fontes; o contrato externo também declara `finding_ids` e `comparison_scope`. O validador verifica existência e vínculo das evidências, pertinência etária da fonte para 65+, compatibilidade da comparação, categoria da fonte e revisão obrigatória. Essas regras não comprovam adequação clínica de texto livre: o profissional continua responsável por revisar, adaptar ou rejeitar.

Ambos os agentes mostram origem OpenAI/motor local e motivo de fallback na área profissional, sem acrescentar essa informação ao PDF do cliente. A alternativa local continua sujeita à validação e aprovação. Exceções da integração não são incorporadas integralmente à resposta. Chamadas externas usam `store: false`; isso não representa promessa de retenção zero pelo provedor.

## Compatibilidade e implantação

Sem migrations ou novas variáveis. Os contratos usam colunas JSON existentes; não há reprocessamento de avaliações antigas ou mudança de OCR/cálculos. Publicar backend e assets frontend juntos pelo fluxo habitual. Prompts versionados: `rag-client-1.4.0` e `care-guidance-2.4.0`.

Observações legadas sem fingerprint podem fornecer seu texto aprovado quando ele ainda coincide com o cadastro, sem inventar achados estruturados ausentes. Sugestões de cuidados geradas antes desta atualização, ainda não aprovadas, precisam ser geradas novamente para registrar o vínculo explícito. Conteúdos já aprovados e PDFs históricos não são alterados automaticamente.

## Conferência manual

1. Em Clientes, escolha avaliação com perfil conhecido; em Assistente, gere observação. Confira origem do gerador e expanda “Dados e limites considerados”. Compare valores e classificações com os registros revisados.
2. Use avaliações com segmentos padrão/alto/baixo, dados ausentes, índices WHR/ASMI e origens impressa/derivada/revisada. Ausências não devem virar zero; classificações devem preservar a procedência.
3. Aprove a observação e gere próximos cuidados. Confira vínculo dos textos ao contexto; sem dados sobre hábitos, não deve haver afirmações sobre dieta atual ou sedentarismo. Abra as fontes e confira revisão obrigatória.
4. Repita com observação editada: os cuidados devem usar a versão aprovada, sem presumir aprovação dos achados originais. Aprove cuidados, recarregue e confira texto e fontes persistidos; abra o relatório.
5. Gere cuidados e, em outra aba, altere/aprove novamente a observação ou corrija um dado da avaliação. Tente aprovar a sugestão antiga: deve exigir nova geração, sem gravar cuidados obsoletos.
6. Compare perfis padrão/atleta e equipamentos diferentes: nenhuma comparação numérica automática quando os perfis forem incompatíveis. Confira primeira avaliação e pessoas abaixo/acima de 65 anos.
7. Em homologação, configure motor local ou simule indisponibilidade externa: a origem e o fallback devem ser claros. Falha/rejeição não aprova automaticamente nenhum texto. Não provoque falhas de cobrança em produção.
8. Confira com perfil sem permissão clínica: geração e aprovação continuam restritas no backend. Confira PWA estreita, painel web e PDF; indicadores de provedor permanecem somente na área profissional.

## Cobertura adicionada

`tests/Unit/AssessmentFindingsTest.php`: valores ausentes, procedência das classificações, mudança de contexto, revisão, referências inválidas e histórico incompatível.

`tests/Feature/BioimpedanceAssistantHandoffTest.php`: persistência e passagem aprovada, alteração da observação/medição, versão editada, falha externa e rejeição semântica com alternativa local.

Os testes e o build não foram executados, conforme orientação do `AGENTS.md`. A revisão desta entrega é estática; a homologação dos cenários acima continua necessária antes da produção.
