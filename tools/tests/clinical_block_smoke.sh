#!/usr/bin/env bash
set -euo pipefail

BASE_URL="${BASE_URL:-https://cive.consulmed.me}"
SID="${SID:-OCCLINIC$(date +%s)}"
COOKIE="PHPSESSID=${SID}"

# Requires session bootstrap externally (or run in host with php session access)
# Example bootstrap:
# /usr/bin/php8.3-cli -r 'session_name("PHPSESSID"); session_id("'$SID'"); session_start(); $_SESSION["user_id"]=1; $_SESSION["id"]=1; $_SESSION["username"]="smoke"; $_SESSION["permisos"]= ["administrativo","settings.manage"]; session_write_close();'

request() {
  local method="$1"; shift
  local path="$1"; shift
  local data="${1:-}"
  local code
  if [[ "$method" == "GET" ]]; then
    code=$(curl -s -o /tmp/clinical_smoke.out -w "%{http_code}" -H "Cookie: ${COOKIE}" "${BASE_URL}${path}")
  else
    code=$(curl -s -o /tmp/clinical_smoke.out -w "%{http_code}" -X "$method" -H "Cookie: ${COOKIE}" -H "Content-Type: application/x-www-form-urlencoded" -d "$data" "${BASE_URL}${path}")
  fi
  echo "$method $path -> $code"
}

echo "== Doctores =="
request GET /doctores
request GET /doctores/1

echo "== Cirugias =="
request GET /cirugias
request GET /cirugias/dashboard
request POST /cirugias/datatable 'draw=1&start=0&length=1'

echo "== Examenes =="
request GET /examenes
request GET /examenes/turnero
request GET /imagenes/examenes-realizados
request GET /examenes/turnero-data
request POST /examenes/kanban-data '{}'

echo "Done."
