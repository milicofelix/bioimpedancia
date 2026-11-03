# Backup, restauração e recuperação de desastre

Este procedimento cobre banco MySQL e arquivos privados/públicos em `storage/app`. Backups contêm dados pessoais e clínicos e devem receber o mesmo nível de proteção do ambiente de produção.

## Objetivos operacionais

Os valores abaixo são metas iniciais e precisam ser aprovados pelo responsável da operação:

- **RPO sugerido:** até 24 horas de perda máxima de dados.
- **RTO sugerido:** restauração do serviço em até 4 horas após a decisão de recuperação.
- **Frequência mínima:** backup diário e antes de migrations ou atualizações relevantes.
- **Teste de restauração:** trimestral e sempre após mudança no processo de backup.
- **Estratégia 3-2-1:** três cópias, em dois meios diferentes, com uma cópia externa ao ambiente principal.

Essas metas não são garantias automáticas. Registre responsável, provedor, horário, duração e resultado de cada execução.

## Conteúdo e exclusões

Incluído:

- Banco configurado em `DB_DATABASE`.
- `storage/app/private`.
- `storage/app/public`.
- Manifesto com data UTC, banco e versão do formato.

Não incluído:

- `.env`, chaves, senhas ou credenciais.
- Logs e caches.
- `vendor`, `node_modules` e assets recompiláveis.
- Imagens temporárias de OCR, que não devem ser retidas permanentemente.

Credenciais e chaves devem ser protegidas pelo gerenciador de segredos e possuir procedimento separado de recuperação.

## Criar backup

O script exige ferramentas MySQL, OpenSSL, `tar` e um diretório absoluto fora da raiz pública. A senha de criptografia deve ficar em arquivo protegido, fora do repositório e diferente da senha do banco.

```bash
export BACKUP_OUTPUT_DIR=/srv/backups/vita-scan
export BACKUP_ENCRYPTION_PASSWORD_FILE=/run/secrets/vita_scan_backup_password
export DB_HOST=127.0.0.1
export DB_PORT=3306
export DB_DATABASE=ricostyemagrecimento
export DB_USERNAME=usuario_backup
export DB_PASSWORD='senha-do-banco'

scripts/backup-production.sh
```

O resultado é um arquivo `tar.gz.enc` e seu checksum SHA-256. Transfira ambos para armazenamento externo criptografado. Nunca envie o arquivo de senha junto com o backup.

## Retenção recomendada

- 7 backups diários.
- 4 backups semanais.
- 12 backups mensais.
- Backups adicionais associados a incidentes ou mudanças críticas conforme obrigação aplicável.

A exclusão deve considerar solicitações de titulares, retenção legal e a impossibilidade operacional de remover seletivamente um registro de cópias imutáveis. Dados restaurados voltam a se submeter às exclusões e anonimizações já solicitadas.

## Restaurar

Restauração é destrutiva para o banco de destino. Execute somente em janela aprovada, com backup de segurança recente e confirmação explícita.

1. Confirme o incidente, o ponto de restauração e o responsável pela autorização.
2. Registre checksum e localização da cópia escolhida.
3. Crie um backup do estado atual, mesmo que degradado.
4. Coloque a aplicação em manutenção.
5. Configure as variáveis e execute o script.

```bash
php artisan down --render="errors::503"

export RESTORE_CONFIRMATION=RESTORE_VITA_SCAN
export RESTORE_ARCHIVE=/srv/backups/vita-scan/vita-scan-AAAAMMDDTHHMMSSZ.tar.gz.enc
export RESTORE_CHECKSUM="$RESTORE_ARCHIVE.sha256"
export BACKUP_ENCRYPTION_PASSWORD_FILE=/run/secrets/vita_scan_backup_password
export DB_HOST=127.0.0.1
export DB_PORT=3306
export DB_DATABASE=ricostyemagrecimento
export DB_USERNAME=usuario_restore
export DB_PASSWORD='senha-do-banco'

scripts/restore-production.sh
```

O script:

- Confere confirmação explícita, checksum, criptografia, manifesto e banco de destino.
- Restaura o banco.
- Copia arquivos armazenados no backup sem apagar automaticamente arquivos atuais adicionais.
- Limpa caches da aplicação.
- Mantém o sistema em manutenção para validação humana.

## Validação antes de reabrir

1. Conferir migrations e conexão com banco.
2. Conferir quantidade amostral de clientes, avaliações, triagens e auditorias.
3. Abrir um cliente sintético e um PDF sem compartilhar externamente.
4. Confirmar permissões e módulos.
5. Confirmar scheduler, filas, e-mail e integrações.
6. Executar `scripts/operational-readiness-check.sh` contra o ambiente restaurado.
7. Registrar evidências, horário e responsável.
8. Somente então executar `php artisan up`.

## Exercício trimestral

Restaure a cópia em ambiente isolado, nunca sobre produção. Meça RPO/RTO reais, valide os itens acima e registre falhas. Apague o ambiente de exercício de forma segura ao finalizar.
