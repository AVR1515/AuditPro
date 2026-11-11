#!/usr/bin/env bash
set -euo pipefail

BASE_URL="${AUDITPRO_HTTP_URL:-http://127.0.0.1:8787}"
tmp="$(mktemp -d)"
trap 'rm -rf "$tmp"' EXIT
cookies="$tmp/cookies.txt"

# Página pública de acceso, cookie de sesión y CSRF generado por servidor real.
curl --fail --silent --show-error -c "$cookies" "$BASE_URL/index.php" > "$tmp/login.html"
grep -q 'Iniciar sesión' "$tmp/login.html"
grep -q 'Saltar al contenido principal' "$tmp/login.html"
grep -q 'id="main-content"' "$tmp/login.html"
csrf="$(grep -o 'name="csrf" value="[^"]*"' "$tmp/login.html" | head -n1 | cut -d '"' -f 4)"
test -n "$csrf"

curl --silent --show-error -b "$cookies" -c "$cookies" -D "$tmp/headers.txt" \
  -o "$tmp/post.html" \
  --data-urlencode "csrf=$csrf" \
  --data-urlencode 'op=login' \
  --data-urlencode 'email=auditor@example.test' \
  --data-urlencode 'password=TestPass123456!' \
  "$BASE_URL/index.php"
grep -Eq '^HTTP/1\.[01] 303|^HTTP/1\.[01] 302' "$tmp/headers.txt"
curl --fail --silent --show-error -b "$cookies" "$BASE_URL/index.php" > "$tmp/auditor.html"
grep -q 'Plan y expedientes' "$tmp/auditor.html"

# La navegación manual a vistas administrativas tampoco debe revelar sus contenidos.
curl --fail --silent --show-error -b "$cookies" "$BASE_URL/index.php?view=policies" > "$tmp/blocked-policies.html"
if grep -q 'Políticas internas y versiones' "$tmp/blocked-policies.html"; then
  echo 'ERROR: acceso de auditor al catálogo de políticas' >&2
  exit 1
fi
curl --fail --silent --show-error -b "$cookies" "$BASE_URL/index.php?view=unit-access" > "$tmp/blocked-units.html"
if grep -q 'Permisos por unidad auditable' "$tmp/blocked-units.html"; then
  echo 'ERROR: acceso de auditor a permisos de unidad' >&2
  exit 1
fi
curl --fail --silent --show-error -b "$cookies" "$BASE_URL/index.php?view=retention" > "$tmp/blocked-retention.html"
if grep -q 'Retención de evidencias' "$tmp/blocked-retention.html"; then
  echo 'ERROR: acceso de auditor a retención' >&2
  exit 1
fi
curl --fail --silent --show-error -b "$cookies" "$BASE_URL/index.php?view=readiness" > "$tmp/blocked-readiness.html"
if grep -q 'Preparación para el piloto y producción' "$tmp/blocked-readiness.html"; then
  echo 'ERROR: acceso de auditor al diagnóstico de lanzamiento' >&2
  exit 1
fi

# Sesión independiente de un supervisor limitado a una unidad.
supervisorCookies="$tmp/supervisor-cookies.txt"
curl --fail --silent --show-error -c "$supervisorCookies" "$BASE_URL/index.php" > "$tmp/supervisor-login.html"
supervisorCsrf="$(grep -o 'name="csrf" value="[^"]*"' "$tmp/supervisor-login.html" | head -n1 | cut -d '"' -f 4)"
test -n "$supervisorCsrf"
curl --silent --show-error -b "$supervisorCookies" -c "$supervisorCookies" \
  -D "$tmp/supervisor-headers.txt" -o "$tmp/supervisor-post.html" \
  --data-urlencode "csrf=$supervisorCsrf" \
  --data-urlencode 'op=login' \
  --data-urlencode 'email=supervisor@example.test' \
  --data-urlencode 'password=TestPass123456!' "$BASE_URL/index.php"
grep -Eq '^HTTP/1\.[01] 303|^HTTP/1\.[01] 302' "$tmp/supervisor-headers.txt"
curl --fail --silent --show-error -b "$supervisorCookies" \
  "$BASE_URL/index.php?view=my-units" > "$tmp/scoped.html"
grep -q 'Mis unidades y riesgos' "$tmp/scoped.html"
grep -q 'Liquidez' "$tmp/scoped.html"
grep -q 'Incidencias' "$tmp/scoped.html"
grep -q 'Guardar medición' "$tmp/scoped.html"
grep -q 'Cobertura de auditorías por unidad' "$tmp/scoped.html"
grep -q 'Control compartido' "$tmp/scoped.html"
grep -q 'Obligaciones relacionadas con mis riesgos' "$tmp/scoped.html"
grep -q 'Plan operativo de expedientes autorizados' "$tmp/scoped.html"
if grep -q 'Auditoría privada' "$tmp/scoped.html"; then
  echo 'ERROR: plan operativo muestra expediente ajeno a supervisor restringido' >&2
  exit 1
fi
grep -q 'Monitoreo' "$tmp/scoped.html"
if grep -q 'Obligación privada' "$tmp/scoped.html"; then
  echo 'ERROR: obligación fuera de unidad expuesta a supervisor restringido' >&2
  exit 1
fi
if grep -q 'Prueba privada' "$tmp/scoped.html"; then
  echo 'ERROR: prueba de expediente ajeno expuesta a supervisor restringido' >&2
  exit 1
fi
if grep -q 'Control ajeno' "$tmp/scoped.html"; then
  echo 'ERROR: control exclusivo de riesgo ajeno expuesto a supervisor restringido' >&2
  exit 1
fi
if grep -q 'Indicador reservado' "$tmp/scoped.html"; then
  echo 'ERROR: KRI de otra unidad expuesto a supervisor restringido' >&2
  exit 1
fi
if grep -q 'Riesgo B' "$tmp/scoped.html"; then
  echo 'ERROR: riesgo fuera del ámbito expuesto a supervisor restringido' >&2
  exit 1
fi
curl --fail --silent --show-error -b "$supervisorCookies" \
  "$BASE_URL/index.php?view=templates" > "$tmp/supervisor-templates.html"
if grep -q 'Catálogo de procedimientos' "$tmp/supervisor-templates.html"; then
  echo 'ERROR: supervisor restringido ve catálogo global de plantillas' >&2
  exit 1
fi
curl --fail --silent --show-error -b "$supervisorCookies" \
  "$BASE_URL/index.php?view=policies" > "$tmp/supervisor-policies.html"
if grep -q 'Políticas internas y versiones' "$tmp/supervisor-policies.html"; then
  echo 'ERROR: supervisor restringido ve políticas globales' >&2
  exit 1
fi

# Roles especializados: acceso a GRC sin facultades administrativas globales.
for specialist in riesgos cumplimiento; do
  specialistCookies="$tmp/$specialist-cookies.txt"
  curl --fail --silent --show-error -c "$specialistCookies" \
    "$BASE_URL/index.php" > "$tmp/$specialist-login.html"
  specialistCsrf="$(grep -o 'name="csrf" value="[^"]*"' \
    "$tmp/$specialist-login.html" | head -n1 | cut -d '"' -f 4)"
  test -n "$specialistCsrf"
  curl --silent --show-error -b "$specialistCookies" -c "$specialistCookies" \
    -D "$tmp/$specialist-headers.txt" -o "$tmp/$specialist-post.html" \
    --data-urlencode "csrf=$specialistCsrf" --data-urlencode 'op=login' \
    --data-urlencode "email=$specialist@example.test" \
    --data-urlencode 'password=TestPass123456!' "$BASE_URL/index.php"
  grep -Eq '^HTTP/1\.[01] 303|^HTTP/1\.[01] 302' "$tmp/$specialist-headers.txt"
  curl --fail --silent --show-error -b "$specialistCookies" \
    "$BASE_URL/index.php?view=grc" > "$tmp/$specialist-grc.html"
  grep -q 'Riesgos y cumplimiento' "$tmp/$specialist-grc.html"
  curl --fail --silent --show-error -b "$specialistCookies" \
    "$BASE_URL/index.php?view=unit-access" > "$tmp/$specialist-unit-access.html"
  if grep -q 'Permisos por unidad auditable' "$tmp/$specialist-unit-access.html"; then
    echo "ERROR: $specialist accedió a permisos administrativos" >&2
    exit 1
  fi
done

# Un formulario sin CSRF válido no debe ejecutar mutaciones.
curl --fail --silent --show-error -b "$cookies" \
  --data-urlencode 'csrf=no-es-valido' --data-urlencode 'op=password' \
  "$BASE_URL/index.php" > "$tmp/invalid-csrf.html"
grep -q 'La sesión del formulario venció' "$tmp/invalid-csrf.html"

# Los archivos privados nunca deben exponerse como recursos web.
status="$(curl --silent --output /dev/null --write-out '%{http_code}' "$BASE_URL/private/schema.sql")"
test "$status" = 404

echo 'OK: inicio de sesión HTTP, aislamiento de módulos, CSRF y raíz pública.'
