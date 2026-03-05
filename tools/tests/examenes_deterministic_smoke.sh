#!/usr/bin/env bash
set -euo pipefail

BASE_URL="${BASE_URL:-https://cive.consulmed.me}"
SID="${SID:-OCEXAM$(date +%s)}"
COOKIE="PHPSESSID=${SID}"

if [[ -z "${BOOTSTRAPPED:-}" ]]; then
  echo "[warn] BOOTSTRAPPED not set. Ensure session $SID exists on target host with auth permissions."
fi

echo "== fixture from /examenes/kanban-data =="
JSON=$(curl -s -X POST -H "Cookie: ${COOKIE}" -H "Content-Type: application/json" -d '{}' "${BASE_URL}/examenes/kanban-data")
read ID HC EST <<< "$(printf '%s' "$JSON" | python3 -c 'import sys,json;j=json.load(sys.stdin);a=j.get("data",[]);i=a[0] if a else {};print(i.get("id",""), i.get("hc_number",""), (i.get("estado") or ""))')"

if [[ -z "$ID" || -z "$HC" || -z "$EST" ]]; then
  echo "[error] fixture not found (id/hc/estado)"
  exit 2
fi

echo "fixture id=$ID hc=$HC estado=$EST"

echo "== GET /examenes/api/estado =="
curl -s -o /tmp/exam_get.out -w "HTTP %{http_code}\n" -H "Cookie: ${COOKIE}" "${BASE_URL}/examenes/api/estado?hc_number=${HC}"
head -c 180 /tmp/exam_get.out; echo

echo "== POST /examenes/api/estado (same-state deterministic write) =="
REQ='{"id":'"$ID"',"estado":"'"$EST"'","observacion":"smoke-deterministic"}'
curl -s -o /tmp/exam_post1.out -w "HTTP %{http_code}\n" -X POST -H "Cookie: ${COOKIE}" -H "Content-Type: application/json" -d "$REQ" "${BASE_URL}/examenes/api/estado"
head -c 220 /tmp/exam_post1.out; echo

echo "== POST /examenes/actualizar-estado (same-state deterministic write) =="
REQ2='{"id":'"$ID"',"estado":"'"$EST"'","origen":"smoke"}'
curl -s -o /tmp/exam_post2.out -w "HTTP %{http_code}\n" -X POST -H "Cookie: ${COOKIE}" -H "Content-Type: application/json" -d "$REQ2" "${BASE_URL}/examenes/actualizar-estado"
head -c 220 /tmp/exam_post2.out; echo

echo "Done."
