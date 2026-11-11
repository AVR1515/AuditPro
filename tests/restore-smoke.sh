#!/usr/bin/env bash
# Comprobación de restauración exclusivamente con datos ficticios de auditpro_test.
# Nunca permite crear, borrar ni restaurar una base de producción.
set -euo pipefail
source_database="${AUDITPRO_TEST_DATABASE:-auditpro_test}"
restore_database="auditpro_restore_test"
if [[ "$source_database" != "auditpro_test" ]]; then
  echo "Rechazado: solo puede restaurarse auditpro_test." >&2
  exit 2
fi
for command in mysql mysqldump; do
  command -v "$command" >/dev/null || { echo "Falta $command" >&2; exit 2; }
done
host="${AUDITPRO_TEST_HOST:-127.0.0.1}"
port="${AUDITPRO_TEST_PORT:-3306}"
user="${AUDITPRO_TEST_USER:-root}"
export MYSQL_PWD="${AUDITPRO_TEST_PASSWORD:-}"
args=(--protocol=tcp --host="$host" --port="$port" --user="$user")
# No se guardan archivos SQL temporales ni se imprimen contraseñas.
mysql "${args[@]}" -e "DROP DATABASE IF EXISTS auditpro_restore_test; CREATE DATABASE auditpro_restore_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
cleanup() {
  mysql "${args[@]}" -e 'DROP DATABASE IF EXISTS auditpro_restore_test;' >/dev/null 2>&1 || true
}
trap cleanup EXIT
mysqldump "${args[@]}" --single-transaction --quick --no-tablespaces --skip-lock-tables "$source_database" |
  mysql "${args[@]}" "$restore_database"
# Comparaciones por tabla, claves, huellas y bytes BLOB; no exponer datos personales.
tables=(users audits tests attachments audit_log action_risk_reviews
  evidence_request_responses attachment_purge_authorizations
  pilot_gate_signoffs scheduled_job_runs)
for table in "${tables[@]}"; do
  source_count="$(mysql "${args[@]}" -N -B "$source_database" -e "SELECT COUNT(*) FROM \`$table\`")"
  restored_count="$(mysql "${args[@]}" -N -B "$restore_database" -e "SELECT COUNT(*) FROM \`$table\`")"
  if [[ "$source_count" != "$restored_count" ]]; then
    echo "Error restaurando $table: diferencias de recuento." >&2
    exit 1
  fi
done
attachments_query="SELECT CONCAT(COUNT(*),':',
 COALESCE(SUM(LENGTH(content)),0),':',
 COALESCE(SUM(purged_at IS NOT NULL),0),':',
 COALESCE(SUM(CASE WHEN content IS NOT NULL AND sha256=SHA2(content,256) THEN 1 ELSE 0 END),0),':',
 COALESCE(SUM(CRC32(CONCAT(id,':',sha256,':',COALESCE(HEX(content),'')))),0))
 FROM attachments"
original="$(mysql "${args[@]}" -N -B "$source_database" -e "$attachments_query")"
recovered="$(mysql "${args[@]}" -N -B "$restore_database" -e "$attachments_query")"
if [[ "$original" != "$recovered" ]]; then
  echo "Error: contenido, metadatos o huellas de evidencias difieren." >&2
  exit 1
fi
echo 'OK: restauración aislada de base de prueba, recuentos, BLOB y SHA-256 coinciden.'
echo 'Esta comprobación NO equivale a restaurar los respaldos reales de la organización.'
