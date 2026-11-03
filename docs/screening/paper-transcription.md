# Triagem com formulário em papel

## Fluxo operacional

1. O cliente responde ao formulário físico na clínica. O papel deve identificar cliente, data e versão do instrumento, além dos consentimentos pertinentes.
2. Em Clientes > Triagens > Nova aplicação, o profissional escolhe a mesma versão aprovada e a data em que o cliente respondeu. A aplicação não gera convite, QR Code ou token público.
3. “Registrar respostas do papel” cria um lançamento interno pendente. “Abrir questionário” abre uma página autenticada para copiar as alternativas exatamente como assinaladas.
4. Na finalização, a equipe confirma identidade/versão, consentimento no documento físico, responsável legal quando aplicável e conferência das respostas. O sistema calcula o resultado pelo motor existente e registra respostas, autor da transcrição e auditoria na mesma transação.
5. Abra o resultado ou o PDF pelo histórico. O profissional pode baixar o PDF e entregá-lo/enviá-lo ao cliente; o sistema não envia mensagens automaticamente. Nenhuma nova funcionalidade de compartilhamento público do resultado foi criada.

Não se deve completar itens em branco nem interpretar marcações ambíguas. O instrumento visual continua descritivo, e a pontuação dos demais questionários não foi alterada.

## Segurança e rastreabilidade

- Acesso de administrador ou profissional ativo. Profissional consulta/transcreve somente as aplicações solicitadas por ele; administrador tem acesso às demais conforme a política existente. Recepção e visualizadores não ganharam permissões clínicas.
- `access_mode=paper_transcription`; `public_token_hash`, `expires_at`, `last_sent_at` ficam nulos e `send_count=0`. O acesso interno está sujeito à autenticação, ao estado da sessão e ao prazo de retenção, não à expiração de convite.
- `paper_completed_on` diferencia o dia da resposta física da data/hora da finalização no sistema. A condição de menor é calculada na data do papel, não apenas na idade atual.
- `paper_consent_confirmed_at` registra a conferência pela equipe. `consent_accepted_at`, `identity_verified_at` e `guardian_consent_accepted_at` não são artificialmente preenchidos como se o cliente tivesse aceitado digitalmente.
- O documento físico não é enviado nem armazenado pelo sistema. Alternativas não finalizadas não têm salvamento automático. Sair/recarregar antes de concluir exige transcrever novamente; a aplicação pendente pode ser retomada no histórico.
- Lançamentos pendentes podem ser cancelados com motivo e auditoria, sem exclusão física. Finalizados permanecem imutáveis. Itens internos pendentes também entram na política de retenção/anomização já existente quando o prazo terminar.
- Convites online anteriores e rotas legadas são preservados. A interface nova não cria/reenvia convites; aplicações internas não podem ser convertidas em convites pelas rotas antigas.

## Ativação

Publicar código e assets pelo processo habitual e executar `php artisan migrate --force` no ambiente correto, após o backup habitual. Não executar `migrate:fresh`. A migration `2026_11_03_000002_add_paper_transcription_to_screening_sessions` acrescenta três campos reversíveis sem alterar sessões antigas. Não há novas variáveis de ambiente ou necessidade de reseedar a base.

## Validação manual

1. Entre como profissional e abra Clientes > cliente ativo > Triagens. Confirme a ausência de modos “Enviar ao cliente”, validade de link, QR Code e compartilhamento do questionário.
2. Selecione versão aprovada e data do papel. Crie e abra o questionário. Confira cliente, data, versão e texto das perguntas; não deve haver confirmação pública de nascimento nem aceite em nome do cliente.
3. Tente concluir sem uma resposta ou sem as conferências: deve impedir a gravação e preservar as seleções para correção. Não deve haver respostas parciais nem falsa sessão finalizada.
4. Transcreva, confira e conclua. Confira resultado, histórico atualizado ao retornar à aba, detalhes profissionais e PDF. No PDF, confira visualmente a identificação separada das datas e a paginação.
5. Recarregue a página e reabra pelo histórico: resultado e autor devem persistir, sem permitir nova submissão. Em uma aplicação apenas pendente, reabra o questionário e confirme que escolhas não submetidas não foram salvas.
6. Crie outro lançamento e cancele com motivo. Deve permanecer no histórico e não aceitar transcrição. Teste menor de idade com consentimento do responsável, data futura, cliente inativo e versão não aprovada.
7. Abra o endereço interno deslogado, como outro profissional e como perfil de recepção: deve exigir login ou negar acesso, conforme o caso. Administrador mantém a permissão já definida. Com módulo desativado, as rotas permanecem bloqueadas.
8. Confira histórico online anterior e seus PDFs. Os convites já existentes não são apagados. Em homologação, confira perda de conexão sem gravação falsa ou reenvio automático.

Foram acrescentados cenários PHPUnit para entrada interna, retomada, finalização, atomicidade, consentimento, propriedade, menores, cancelamento e bloqueio de convite público. Testes, build e renderização de PDF não foram executados, seguindo o `AGENTS.md`; a homologação manual acima continua necessária.
