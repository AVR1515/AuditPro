#!/usr/bin/env bash
# Sólo lectura. No envía credenciales, no ignora errores TLS y no modifica datos.
set -euo pipefail
BASE="${1:-${AUDITPRO_BASE_URL:-}}"
if [[ -z "$BASE" || "$BASE" != https://* ]]; then
  echo 'Uso: bash tests/deployment-probe.sh https://servidor.example/auditpro' >&2
  exit 2
fi
BASE="${BASE%/}"
dir="$(mktemp -d)"
trap 'rm -rf "$dir"' EXIT

# Falla si el certificado no es válido o la aplicación no responde.
curl --fail --silent --show-error --max-time 20 -D "$dir/headers" \
  -o "$dir/home" "$BASE/index.php"
check_header() {
  if ! grep -Eiq "^$1:" "$dir/headers"; then
    echo "FALTA cabecera: $1" >&2
    exit 1
  fi
}
check_header 'content-security-policy'
check_header 'strict-transport-security'
check_header 'x-content-type-options'
check_header 'referrer-policy'
check_header 'x-frame-options'
check_header 'cache-control'

for path in 'private/schema.sql' 'private/config.example.php' \
  'private/upgrade-028.sql' 'private/recovery.php' '.git/config'; do
  status="$(curl --silent --show-error --max-time 15 \
    --output /dev/null --write-out '%{http_code}' "$BASE/$path")"
  if [[ "$status" != 403 && "$status" != 404 ]]; then
    echo "ERROR: $path devolvió HTTP $status; esperaba 403/404" >&2
    exit 1
  fi
done

echo 'OK: certificado TLS válido, cabeceras y bloqueo de archivos privados.'
echo 'PENDIENTE MANUAL: roles, restauración de respaldos, políticas y firma del piloto.'
