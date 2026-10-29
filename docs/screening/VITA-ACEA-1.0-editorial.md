# VITA-ACEA-1.0 — definição clínica e editorial

> Esta definição permanece preservada para o histórico. A escolha visual descritiva foi acrescentada somente na versão 1.1, documentada em `VITA-ACEA-1.1-visual-choice.md`.

## Situação editorial

- Código: `VITA-ACEA`.
- Versão inicial: `VITA-ACEA-1.0`.
- Status: pendente de aprovação por profissional habilitado.
- Natureza: questionário descritivo de triagem, informal e não validado clinicamente.
- Período de recordação proposto: últimos 14 dias.
- Pontuação possível: 0 a 30 pontos.

O instrumento não pode ser apresentado como teste diagnóstico, avaliação psicológica ou confirmação de ansiedade, compulsão alimentar ou transtorno alimentar.

## Afirmações permitidas

- “A pontuação resume a frequência das respostas informadas no questionário.”
- “O resultado pertence a uma faixa descritiva deste questionário.”
- “Algumas respostas merecem conversa individual com profissional habilitado.”
- “O resultado não substitui entrevista, avaliação clínica ou instrumento validado.”

## Afirmações proibidas

- “Você tem ansiedade.”
- “Você tem compulsão alimentar.”
- “Seu grau de ansiedade é baixo, moderado ou alto.”
- “O resultado confirma risco clínico.”
- “A pontuação recomenda tratamento, dieta, medicamento ou suplementação.”

## Perfis e responsabilidades

### Profissional

- Pode solicitar a aplicação após a aprovação editorial do instrumento.
- Pode acessar respostas e faixa descritiva.
- Pode contextualizar o resultado e registrar observação profissional.
- Deve encaminhar para avaliação adequada quando houver sofrimento importante, perda de controle recorrente ou comportamentos compensatórios relatados.

### Administrador

- Pode administrar configurações e versões do instrumento.
- Pode solicitar aplicações, acompanhar respostas e interpretar resultados quando também atuar no atendimento da unidade.
- Toda criação, interpretação, aprovação ou correção permanece vinculada nominalmente à conta administradora e registrada em auditoria.

### Recepção

- Não pode aplicar, interpretar ou consultar respostas deste questionário.
- Em fase futura, poderá apenas orientar o cliente a acessar um convite já criado por profissional autorizado, sem visualizar respostas ou pontuação.

### Visualização

- Não pode aplicar, interpretar ou consultar respostas.

### Cliente

- Responde apenas a própria aplicação por acesso temporário e consentido.
- Visualiza somente a devolutiva destinada ao cliente.

## Escala de resposta

| Valor | Resposta |
| ---: | --- |
| 0 | Nunca ou raramente |
| 1 | Às vezes |
| 2 | Frequentemente |
| 3 | Quase sempre |

## Perguntas propostas

1. Nos últimos 14 dias, com que frequência você sentiu ansiedade ou inquietação durante o dia?
2. Quando sentiu ansiedade, teve vontade de comer mesmo sem perceber fome física?
3. Teve dificuldade para controlar a vontade de comer doces ou outros alimentos?
4. Comeu para tentar aliviar tristeza, estresse, solidão ou preocupação?
5. Sentiu perda de controle e continuou comendo mesmo após perceber saciedade?
6. Depois de comer, sentiu culpa ou arrependimento?
7. Pensou em comida várias vezes ao dia, mesmo enquanto realizava outras atividades?
8. Em momentos de preocupação, percebeu que comeu mais rápido ou em maior quantidade?
9. Teve episódios repetidos em que sentiu que não conseguia parar de comer?
10. A sua relação com a comida prejudicou sua autoestima ou sua rotina?

As perguntas 2, 5, 6, 9 e 10 devem ser destacadas apenas para conversa individual. Uma resposta isolada não autoriza conclusão diagnóstica.

## Faixas descritivas

| Pontuação | Descrição |
| ---: | --- |
| 0–7 | Poucos sinais relatados |
| 8–15 | Alguns sinais de atenção |
| 16–22 | Sinais frequentes |
| 23–30 | Muitos sinais relatados |

As faixas são próprias deste questionário, não são pontos de corte validados e não representam grau clínico.

## Regra determinística de pontuação

- Versão das regras: `VITA-ACEA-SCORING-1.0`.
- Método: soma determinística interna, sem inteligência artificial.

- Cada uma das dez perguntas exige exatamente uma alternativa cadastrada na mesma versão.
- A pontuação de cada resposta vem exclusivamente do valor persistido da alternativa; valores enviados pelo navegador não são aceitos como fonte de verdade.
- A pontuação total é a soma simples das dez alternativas, de 0 a 30 pontos.
- O total deve corresponder a exatamente uma faixa descritiva, sem lacunas ou sobreposições.
- As perguntas 2, 5, 6, 9 e 10 geram marcação para conversa individual quando a resposta possui pontuação maior que zero.
- A marcação individual não modifica a soma e não representa diagnóstico ou classificação clínica.
- Perguntas, alternativas e faixas tornam-se imutáveis após a ativação ou primeira aplicação da versão.
- A finalização grava respostas, snapshots, pontuação, faixa e auditoria na mesma transação e só pode ocorrer uma vez.
- A aplicação finalizada preserva a versão e um snapshot das regras utilizadas, inclusive códigos das perguntas de atenção e limites da pontuação.

## Aviso obrigatório ao cliente

> Este questionário é uma triagem descritiva informal e não foi validado clinicamente. A pontuação não diagnostica ansiedade, compulsão alimentar ou qualquer outra condição de saúde. O resultado deve ser contextualizado por profissional habilitado e não substitui entrevista, avaliação clínica ou instrumento validado.

## Devolutiva imediata ao cliente

- Exibir confirmação da conclusão, pontuação total, faixa descritiva e mensagem neutra da faixa.
- Orientar sempre a conversa com o profissional responsável.
- Quando houver marcação em pergunta de atenção, informar apenas que algumas respostas merecem conversa individual, sem apresentar diagnóstico ou mensagem alarmista.
- Permitir abrir o mesmo conteúdo em PDF enquanto o acesso temporário verificado estiver válido.
- A tela pública não revela identificadores internos; o PDF identificado exibe apenas o nome necessário para vincular o documento ao cliente.

## Separação entre documento do cliente e visão profissional

- O PDF do cliente identifica cliente, preenchimento, questionário, profissional solicitante e código do documento.
- As respostas aparecem sem pontuação individual, regra de correção, marcador interno de atenção ou histórico de auditoria.
- A visão profissional autenticada reúne respostas, destaques de atenção, consentimento, datas e auditoria.
- Observações e encaminhamentos são registros internos separados da aplicação finalizada e cada alteração gera auditoria.
- O conteúdo interno não deve ser incorporado ao PDF entregue ao cliente.

## Histórico e comparação longitudinal

- Exibir aplicações em ordem cronológica inversa, preservando status, versão, solicitante e datas.
- Comparar somente aplicações finalizadas consecutivas do mesmo código e versão.
- Quando as versões forem diferentes, informar que não existe comparação direta válida.
- Descrever apenas aumento, diminuição ou estabilidade da pontuação, sem concluir melhora ou piora clínica.
- Registrar em auditoria quem abriu o resultado interno e quem solicitou a entrega do PDF.

## Segurança, privacidade e retenção

- O consentimento específico deve ser aceito antes da primeira resposta; em aplicações de menores, o responsável legal também deve se identificar e aceitar um consentimento próprio.
- Somente administradores ativos e profissionais ativos podem criar convites e consultar resultados internos. Recepção e visualização não acessam respostas ou pontuação.
- Convites usam token aleatório armazenado apenas como hash, possuem expiração, limite de tentativas, revogação e uma única finalização.
- Páginas e PDFs públicos usam cabeçalhos de não indexação e não cache; o PDF é renderizado sob demanda e não é salvo em diretório público.
- Textos das respostas, observações e encaminhamentos profissionais são criptografados em repouso. Pontuação e faixa permanecem estruturadas para cálculo determinístico, comparação e auditoria autorizada.
- A chave `APP_KEY` usada pela criptografia deve permanecer protegida, estável e disponível nos procedimentos de backup e recuperação; sua perda torna o conteúdo criptografado irrecuperável.
- Logs e auditorias não recebem o conteúdo integral das respostas, observações ou encaminhamentos. Notificações e textos de compartilhamento não incluem pontuação.
- A retenção padrão é de 1.825 dias e pode ser ajustada por `SCREENING_RETENTION_DAYS`. Após o prazo, a rotina diária desvincula a triagem do cliente, revoga o token, remove anotações livres e higieniza metadados de auditoria.
- A anonimização LGPD do cadastro executa a mesma rotina imediatamente para todas as triagens vinculadas.
- A aplicação finalizada é imutável. Ajustes de observações e encaminhamentos são registrados separadamente e auditados sem copiar o conteúdo sensível para o evento.
- Aplicações para menores podem ser desabilitadas por `SCREENING_MINOR_APPLICATIONS_ENABLED=false`.

## Aprovação profissional necessária

Antes de ativar esta versão, registrar:

- Nome do profissional responsável: ____________________________________
- Profissão e registro profissional: ___________________________________
- Data da revisão: ____/____/________
- Período de 14 dias aprovado: sim ( ) não ( )
- Perguntas e linguagem aprovadas: sim ( ) não ( )
- Faixas descritivas aprovadas: sim ( ) não ( )
- Avisos e limites aprovados: sim ( ) não ( )
- Observações: ________________________________________________________
- Assinatura ou aprovação eletrônica: __________________________________

Enquanto esses campos não forem formalmente registrados no sistema, a versão deve permanecer com status `pending_professional_approval` e indisponível para aplicação.
