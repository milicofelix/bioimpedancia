#!/usr/bin/env sh
set -eu

base_url=${1:-${APP_URL:-}}
if [ -z "$base_url" ]; then
    echo "Uso: scripts/operational-readiness-check.sh https://sistema.exemplo" >&2
    exit 1
fi

base_url=${base_url%/}

check_endpoint() {
    endpoint=$1
    expected_status=$2
    status=$(curl --silent --show-error --output /dev/null --write-out '%{http_code}' --max-time 15 "$base_url$endpoint")
    if [ "$status" != "$expected_status" ]; then
        echo "Falha em $endpoint: HTTP $status (esperado $expected_status)" >&2
        exit 1
    fi
    echo "OK $endpoint: HTTP $status"
}

check_endpoint /up 200
check_endpoint /up/ready 200

echo "Verificação operacional concluída sem expor dados clínicos."
