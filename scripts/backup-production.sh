#!/usr/bin/env sh
set -eu

umask 077

require_value() {
    variable_name=$1
    variable_value=$2
    if [ -z "$variable_value" ]; then
        echo "Variável obrigatória ausente: $variable_name" >&2
        exit 1
    fi
}

require_value BACKUP_OUTPUT_DIR "${BACKUP_OUTPUT_DIR:-}"
require_value BACKUP_ENCRYPTION_PASSWORD_FILE "${BACKUP_ENCRYPTION_PASSWORD_FILE:-}"
require_value DB_HOST "${DB_HOST:-}"
require_value DB_PORT "${DB_PORT:-}"
require_value DB_DATABASE "${DB_DATABASE:-}"
require_value DB_USERNAME "${DB_USERNAME:-}"
require_value DB_PASSWORD "${DB_PASSWORD:-}"

for command_name in mysqldump openssl tar; do
    if ! command -v "$command_name" >/dev/null 2>&1; then
        echo "Comando obrigatório indisponível: $command_name" >&2
        exit 1
    fi
done

if [ ! -s "$BACKUP_ENCRYPTION_PASSWORD_FILE" ]; then
    echo "Arquivo de senha de criptografia não encontrado ou vazio." >&2
    exit 1
fi

case "$BACKUP_OUTPUT_DIR" in
    /*) ;;
    *) echo "BACKUP_OUTPUT_DIR deve ser um caminho absoluto fora do diretório público." >&2; exit 1 ;;
esac

mkdir -p "$BACKUP_OUTPUT_DIR"
temporary_directory=$(mktemp -d "${TMPDIR:-/tmp}/vita-scan-backup.XXXXXX")
trap 'rm -rf "$temporary_directory"' EXIT INT TERM

timestamp=$(date -u '+%Y%m%dT%H%M%SZ')
archive_name="vita-scan-${timestamp}.tar.gz.enc"
archive_path="$BACKUP_OUTPUT_DIR/$archive_name"

mkdir -p "$temporary_directory/payload/storage/app"
MYSQL_PWD="$DB_PASSWORD" mysqldump \
    --host="$DB_HOST" \
    --port="$DB_PORT" \
    --user="$DB_USERNAME" \
    --single-transaction \
    --quick \
    --routines \
    --triggers \
    --no-tablespaces \
    "$DB_DATABASE" > "$temporary_directory/payload/database.sql"

if [ -d storage/app/private ]; then
    cp -R storage/app/private "$temporary_directory/payload/storage/app/private"
fi
if [ -d storage/app/public ]; then
    cp -R storage/app/public "$temporary_directory/payload/storage/app/public"
fi

cat > "$temporary_directory/payload/manifest.txt" <<EOF
created_at_utc=$timestamp
database=$DB_DATABASE
application=Vita Scan
format_version=1
EOF

tar -C "$temporary_directory/payload" -czf - . \
    | openssl enc -aes-256-cbc -salt -pbkdf2 -pass "file:$BACKUP_ENCRYPTION_PASSWORD_FILE" -out "$archive_path"

if command -v sha256sum >/dev/null 2>&1; then
    (cd "$BACKUP_OUTPUT_DIR" && sha256sum "$archive_name" > "$archive_name.sha256")
else
    (cd "$BACKUP_OUTPUT_DIR" && shasum -a 256 "$archive_name" > "$archive_name.sha256")
fi

echo "Backup criptografado criado: $archive_path"
echo "Checksum criado: $archive_path.sha256"
