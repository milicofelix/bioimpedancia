# Matriz de permissões

Esta matriz documenta as permissões efetivas do Vita Scan. A autorização do backend é a fonte de verdade; controles ocultos ou desabilitados no frontend servem apenas para orientar a operação.

## Perfis

- **Administrador:** configuração, gestão de usuários, privacidade, operação e recursos clínicos.
- **Profissional:** operação clínica, avaliações, triagens, assistente e relatórios.
- **Recepção:** cadastro, avaliação manual, consulta e compartilhamento, sem alterar resultados clínicos.
- **Visualizador:** consulta autenticada, sem gravações.
- **Público:** acesso somente por token temporário aos fluxos explicitamente públicos.

Todos os perfis autenticados precisam estar ativos. A inativação encerra a sessão no próximo acesso, inclusive quando a sessão já estava aberta em outro dispositivo.

## Operações internas

Legenda: **Sim** = permitido; **Não** = negado; **Cond.** = permitido com condição adicional.

| Recurso ou ação | Administrador | Profissional | Recepção | Visualizador | Condições relevantes |
| --- | :---: | :---: | :---: | :---: | --- |
| Entrar e consultar área interna | Sim | Sim | Sim | Sim | Conta ativa |
| Consultar clientes e histórico disponível | Sim | Sim | Sim | Sim | Conta ativa |
| Cadastrar cliente | Sim | Sim | Sim | Não | Dados e consentimentos válidos |
| Editar perfil, inativar cliente | Sim | Sim | Não | Não | Cliente não anonimizado quando aplicável |
| Exportar dados LGPD | Sim | Não | Não | Não | Ação auditada |
| Anonimizar cliente | Sim | Não | Não | Não | Irreversível; revoga compartilhamentos e preserva auditoria minimizada |
| Criar avaliação manual | Sim | Sim | Sim | Não | Cliente ativo; módulo Avaliação corporal ativo |
| Importar e confirmar imagem/OCR | Sim | Sim | Não | Não | Fluxo clínico exige conferência profissional |
| Corrigir ou cancelar avaliação | Sim | Sim | Não | Não | Motivo obrigatório; ação auditada |
| Gerar, revisar e aprovar conteúdo do assistente | Sim | Sim | Não | Não | Avaliação válida; revisão profissional obrigatória |
| Consultar ou baixar PDF | Sim | Sim | Sim | Sim | Módulo Relatórios e geração de PDF ativos |
| Criar link temporário de relatório | Sim | Sim | Sim | Não | Avaliação não cancelada; validade de 1 a 30 dias |
| Revogar link temporário de relatório | Sim | Sim | Sim | Não | A revogação acompanha a permissão de criação |
| Administrar configurações da clínica e módulos | Sim | Não | Não | Não | Disponível apenas na interface web administrativa |
| Administrar usuários | Sim | Não | Não | Não | Não é permitido inativar o próprio usuário administrador |
| Administrar base de conhecimento | Sim | Não | Não | Não | Publicação e transições auditáveis |
| Consultar painel administrativo e auditoria global | Sim | Não | Não | Não | Dados administrativos não são enviados aos demais perfis |

## Triagens

| Recurso ou ação | Administrador | Profissional | Recepção | Visualizador | Condições relevantes |
| --- | :---: | :---: | :---: | :---: | --- |
| Criar convite de triagem (API legada) | Sim | Sim | Não | Não | Módulo Triagens ativo e conta ativa; removido da interface operacional nova |
| Criar lançamento de formulário em papel | Sim | Sim | Não | Não | Módulo ativo, cliente ativo e versão aprovada |
| Abrir, transcrever ou cancelar lançamento pendente | Sim | Cond. | Não | Não | Profissional somente nas próprias aplicações; conferências do documento obrigatórias para finalizar |
| Consultar, reenviar ou revogar convite legado | Sim | Cond. | Não | Não | Administrador acessa todos; profissional somente os próprios; sessões internas não podem virar convites |
| Revisar resultado e registrar devolutiva | Sim | Cond. | Não | Não | Profissional somente nas sessões solicitadas por ele |
| Baixar PDF da triagem | Sim | Cond. | Não | Não | Mesma regra de propriedade da sessão |
| Aprovar questionário | Sim | Sim | Não | Não | Questionário em estado compatível |

## Acessos públicos por token

| Fluxo | Autenticação | Proteções |
| --- | --- | --- |
| Relatório corporal compartilhado | Token temporário | Expiração, revogação, confirmação antes de abrir, sem cache, sem indexação e auditoria de visualização |
| Questionário de triagem | Token aleatório | Expiração/revogação, verificação de identidade, limite de tentativas e módulo ativo |
| Resultado público da triagem | Token da sessão | Estado válido da sessão, limite de requisições e módulo ativo |

O token concede acesso somente ao recurso específico. Ele não cria sessão interna nem amplia permissões do visitante.

## Restrições complementares

- A ativação de módulos reduz o acesso; nunca amplia o perfil do usuário.
- A PWA reflete as permissões do backend, mas a configuração de módulos permanece na versão web administrativa.
- O modo offline não autoriza gravações clínicas. Operações que dependem do servidor ficam indisponíveis até a reconexão.
- Links de relatório não incluem o token em auditorias ou mensagens persistidas. Para permitir nova cópia por perfis autorizados a compartilhar enquanto o acesso estiver ativo, o token é guardado em coluna criptografada e eliminado quando o link é revogado. O visualizador recebe somente estado, validade e quantidade de acessos.
- Dados administrativos e eventos globais de auditoria são retornados apenas ao administrador.
- Acesso de leitura ao PDF pode registrar emissão/download e, portanto, gera auditoria mesmo sem alterar o conteúdo clínico.

## Roteiro de validação da matriz

1. Criar um usuário de cada perfil e confirmar que todos os usuários ativos conseguem entrar e consultar o conteúdo permitido.
2. Confirmar que o visualizador não consegue criar cliente, avaliação ou compartilhamento, inclusive por requisição direta à rota.
3. Confirmar que a recepção cria cliente, avaliação manual e link temporário, revoga o link, mas não importa imagem, corrige/cancela avaliação ou usa o assistente.
4. Confirmar que o profissional executa operações clínicas e acessa somente as triagens que solicitou.
5. Confirmar que o administrador gerencia usuários, módulos, privacidade, base de conhecimento e todas as triagens.
6. Inativar um usuário que esteja com sessão aberta; na próxima navegação ou requisição ele deve ser desconectado.
7. Desativar cada módulo e confirmar que o recurso correspondente fica indisponível mesmo para administrador.
8. Abrir um compartilhamento válido, revogá-lo e confirmar que o mesmo endereço passa a responder como expirado ou revogado.

## Referências no código

- Perfis e capacidades básicas: `app/Models/User.php`.
- Bloqueio de contas inativas: `app/Http/Middleware/EnsureActiveUser.php` e `routes/web.php`.
- Regras da avaliação corporal: `app/Services/Bioimpedance/BioimpedanceWorkflow.php`.
- Regras de propriedade das triagens: serviços e autorizadores em `app/Services/Screening`.
- Módulos habilitados: `app/Http/Middleware/EnsureClinicModuleEnabled.php`.
- Visibilidade operacional da PWA: `resources/js/pwa/features/clients/clientAccess.js`.
