#!/usr/bin/env sh
set -eu

umask 077

if [ "${RESTORE_CONFIRMATION:-}" != "RESTORE_VITA_SCAN" ]; then
    echo "Restauração bloqueada. Defina RESTORE_CONFIRMATION=RESTORE_VITA_SCAN após confirmar janela e backup de segurança." >&2
    exit 1
fi

require_value() {
    variable_name=$1
    variable_value=$2
    if [ -z "$variable_value" ]; then
        echo "Variável obrigatória ausente: $variable_name" >&2
        exit 1
    fi
}

require_value RESTORE_ARCHIVE "${RESTORE_ARCHIVE:-}"
require_value RESTORE_CHECKSUM "${RESTORE_CHECKSUM:-}"
require_value BACKUP_ENCRYPTION_PASSWORD_FILE "${BACKUP_ENCRYPTION_PASSWORD_FILE:-}"
require_value DB_HOST "${DB_HOST:-}"
require_value DB_PORT "${DB_PORT:-}"
require_value DB_DATABASE "${DB_DATABASE:-}"
require_value DB_USERNAME "${DB_USERNAME:-}"
require_value DB_PASSWORD "${DB_PASSWORD:-}"

for command_name in mysql openssl tar php; do
    if ! command -v "$command_name" >/dev/null 2>&1; then
        echo "Comando obrigatório indisponível: $command_name" >&2
        exit 1
    fi
done

if [ ! -f "$RESTORE_ARCHIVE" ] || [ ! -f "$RESTORE_CHECKSUM" ] || [ ! -s "$BACKUP_ENCRYPTION_PASSWORD_FILE" ]; then
    echo "Arquivo de backup, checksum ou senha de criptografia não encontrado/vazio." >&2
    exit 1
fi

expected_checksum=$(awk 'NR == 1 { print $1 }' "$RESTORE_CHECKSUM")
if command -v sha256sum >/dev/null 2>&1; then
    actual_checksum=$(sha256sum "$RESTORE_ARCHIVE" | awk '{ print $1 }')
else
    actual_checksum=$(shasum -a 256 "$RESTORE_ARCHIVE" | awk '{ print $1 }')
fi
if [ -z "$expected_checksum" ] || [ "$actual_checksum" != "$expected_checksum" ]; then
    echo "Checksum inválido. A restauração foi interrompida antes de alterar dados." >&2
    exit 1
fi

if [ ! -f storage/framework/down ]; then
    echo "Coloque a aplicação em manutenção com 'php artisan down' antes da restauração." >&2
    exit 1
fi

temporary_directory=$(mktemp -d "${TMPDIR:-/tmp}/vita-scan-restore.XXXXXX")
trap 'rm -rf "$temporary_directory"' EXIT INT TERM

decrypted_archive="$temporary_directory/payload.tar.gz"
extracted_directory="$temporary_directory/extracted"
mkdir -p "$extracted_directory"

openssl enc -d -aes-256-cbc -pbkdf2 -pass "file:$BACKUP_ENCRYPTION_PASSWORD_FILE" \
    -in "$RESTORE_ARCHIVE" -out "$decrypted_archive"

if tar -tzf "$decrypted_archive" | awk '
    /^\// { invalid = 1 }
    {
        count = split($0, parts, "/")
        for (index = 1; index <= count; index++) {
            if (parts[index] == "..") {
                invalid = 1
            }
        }
    }
    END { exit invalid ? 0 : 1 }
'; then
    echo "Backup inválido: o arquivo compactado contém caminho inseguro." >&2
    exit 1
fi

tar -C "$extracted_directory" -xzf "$decrypted_archive"

if [ ! -s "$extracted_directory/database.sql" ] || [ ! -s "$extracted_directory/manifest.txt" ]; then
    echo "Backup inválido: banco ou manifesto ausente." >&2
    exit 1
fi

backup_database=$(sed -n 's/^database=//p' "$extracted_directory/manifest.txt" | head -n 1)
if [ "$backup_database" != "$DB_DATABASE" ]; then
    echo "O backup pertence ao banco '$backup_database', mas o destino informado é '$DB_DATABASE'." >&2
    exit 1
fi

MYSQL_PWD="$DB_PASSWORD" mysql \
    --host="$DB_HOST" \
    --port="$DB_PORT" \
    --user="$DB_USERNAME" \
    "$DB_DATABASE" < "$extracted_directory/database.sql"

if [ -d "$extracted_directory/storage/app/private" ]; then
    mkdir -p storage/app/private
    cp -R "$extracted_directory/storage/app/private/." storage/app/private/
fi
if [ -d "$extracted_directory/storage/app/public" ]; then
    mkdir -p storage/app/public
    cp -R "$extracted_directory/storage/app/public/." storage/app/public/
fi

php artisan optimize:clear

echo "Restauração aplicada. A aplicação continua em manutenção. Valide banco, arquivos, permissões e auditoria antes de executar 'php artisan up'."
