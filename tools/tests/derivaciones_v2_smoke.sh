#!/usr/bin/env bash
set -euo pipefail

BASE_URL="${BASE_URL:-http://127.0.0.1:8081}"
DERIVACIONES_BASE_PATH="${DERIVACIONES_BASE_PATH:-/v2/derivaciones}"
LEGACY_DERIVACIONES_BASE_PATH="${LEGACY_DERIVACIONES_BASE_PATH:-/derivaciones}"
EXPECT_LEGACY_REDIRECT="${EXPECT_LEGACY_REDIRECT:-0}"
EXPECT_SCRAP_SUCCESS="${EXPECT_SCRAP_SUCCESS:-0}"
SID="${SID:-}"
FORM_ID="${FORM_ID:-}"
HC_NUMBER="${HC_NUMBER:-}"
DERIVACION_ID="${DERIVACION_ID:-}"
TMP_DIR="${TMP_DIR:-/tmp}"

if [[ -z "${SID}" ]]; then
  echo "[error] SID no definido. Exporta SID=<PHPSESSID> antes de ejecutar."
  exit 2
fi

COOKIE="PHPSESSID=${SID}"
DT_OUT="${TMP_DIR%/}/derivaciones_v2_datatable.json"
SCRAP_OUT="${TMP_DIR%/}/derivaciones_v2_scrap.json"
PDF_OUT="${TMP_DIR%/}/derivaciones_v2_archivo.pdf"
LEGACY_HEADERS_OUT="${TMP_DIR%/}/derivaciones_legacy_headers.txt"
LEGACY_DT_HEADERS_OUT="${TMP_DIR%/}/derivaciones_legacy_dt_headers.txt"

if [[ "${EXPECT_LEGACY_REDIRECT}" == "1" ]]; then
  curl -sS -D "${LEGACY_HEADERS_OUT}" -o /dev/null \
    -H "Cookie: ${COOKIE}" \
    "${BASE_URL}${LEGACY_DERIVACIONES_BASE_PATH}"

  legacy_status="$(awk 'BEGIN{IGNORECASE=1} /^HTTP\// {code=$2} END{print code}' "${LEGACY_HEADERS_OUT}")"
  legacy_location="$(awk 'BEGIN{IGNORECASE=1} /^location:/ {sub(/\r$/, "", $2); print $2}' "${LEGACY_HEADERS_OUT}" | tail -n1)"
  echo "GET ${LEGACY_DERIVACIONES_BASE_PATH} -> ${legacy_status:-<sin_status>} location=${legacy_location:-<sin_location>}"

  if [[ "${legacy_status}" != "302" && "${legacy_status}" != "301" && "${legacy_status}" != "307" && "${legacy_status}" != "308" ]]; then
    echo "[error] legacy UI no redirige (status=${legacy_status:-vacio})."
    cat "${LEGACY_HEADERS_OUT}" || true
    exit 6
  fi

  if [[ "${legacy_location}" != "${DERIVACIONES_BASE_PATH}" ]]; then
    echo "[error] legacy UI redirige a destino inesperado: ${legacy_location:-<vacio>}"
    cat "${LEGACY_HEADERS_OUT}" || true
    exit 7
  fi

  curl -sS -D "${LEGACY_DT_HEADERS_OUT}" -o /dev/null \
    -X POST \
    -H "Cookie: ${COOKIE}" \
    --data-urlencode "draw=1" \
    --data-urlencode "start=0" \
    --data-urlencode "length=1" \
    "${BASE_URL}${LEGACY_DERIVACIONES_BASE_PATH}/datatable"

  legacy_dt_status="$(awk 'BEGIN{IGNORECASE=1} /^HTTP\// {code=$2} END{print code}' "${LEGACY_DT_HEADERS_OUT}")"
  legacy_dt_location="$(awk 'BEGIN{IGNORECASE=1} /^location:/ {sub(/\r$/, "", $2); print $2}' "${LEGACY_DT_HEADERS_OUT}" | tail -n1)"
  echo "POST ${LEGACY_DERIVACIONES_BASE_PATH}/datatable -> ${legacy_dt_status:-<sin_status>} location=${legacy_dt_location:-<sin_location>}"

  if [[ "${legacy_dt_status}" != "307" && "${legacy_dt_status}" != "308" ]]; then
    echo "[error] legacy datatable no usa redirect temporal esperado (307/308)."
    cat "${LEGACY_DT_HEADERS_OUT}" || true
    exit 8
  fi

  if [[ "${legacy_dt_location}" != "${DERIVACIONES_BASE_PATH}/datatable" ]]; then
    echo "[error] legacy datatable redirige a destino inesperado: ${legacy_dt_location:-<vacio>}"
    cat "${LEGACY_DT_HEADERS_OUT}" || true
    exit 9
  fi
fi

datatable_status=$(
  curl -sS -o "${DT_OUT}" -w "%{http_code}" \
    -X POST \
    -H "Cookie: ${COOKIE}" \
    --data-urlencode "draw=1" \
    --data-urlencode "start=0" \
    --data-urlencode "length=10" \
    "${BASE_URL}${DERIVACIONES_BASE_PATH}/datatable"
)
echo "POST ${DERIVACIONES_BASE_PATH}/datatable -> ${datatable_status}"

if [[ "${datatable_status}" != "200" ]]; then
  echo "[error] datatable devolvio ${datatable_status}"
  head -c 500 "${DT_OUT}" || true
  echo
  exit 3
fi

read -r inferred_form_id inferred_hc_number inferred_derivacion_id < <(
  python3 - "${DT_OUT}" "${FORM_ID}" "${HC_NUMBER}" "${DERIVACION_ID}" <<'PY'
import json
import re
import sys

path, form_override, hc_override, id_override = sys.argv[1:]
form = form_override.strip()
hc = hc_override.strip()
derivacion_id = id_override.strip()

with open(path, "r", encoding="utf-8") as fh:
    payload = json.load(fh)

rows = payload.get("data") or []
if not isinstance(rows, list):
    rows = []

def row_derivacion_id(row):
    html = str(row.get("archivo_html") or "")
    match = re.search(r"/archivo/(\d+)", html)
    return match.group(1) if match else ""

target_row = None

if derivacion_id:
    for row in rows:
        if not isinstance(row, dict):
            continue
        if row_derivacion_id(row) == derivacion_id:
            target_row = row
            break

if target_row is None and form and hc:
    for row in rows:
        if not isinstance(row, dict):
            continue
        row_form = str(row.get("form_id") or "").strip()
        row_hc = str(row.get("hc_number") or "").strip()
        if row_form == form and row_hc == hc:
            target_row = row
            break

if target_row is None and not (form and hc):
    for row in rows:
        if not isinstance(row, dict):
            continue
        row_form = str(row.get("form_id") or "").strip()
        row_hc = str(row.get("hc_number") or "").strip()
        if row_form and row_hc:
            target_row = row
            break

if target_row is None and rows:
    first = rows[0]
    if isinstance(first, dict):
        target_row = first

if isinstance(target_row, dict):
    row_form = str(target_row.get("form_id") or "").strip()
    row_hc = str(target_row.get("hc_number") or "").strip()
    row_id = row_derivacion_id(target_row)

    if not form and row_form:
        form = row_form
    if not hc and row_hc:
        hc = row_hc
    if not derivacion_id and row_id:
        derivacion_id = row_id

print(form, hc, derivacion_id)
PY
)

FORM_ID="${FORM_ID:-$inferred_form_id}"
HC_NUMBER="${HC_NUMBER:-$inferred_hc_number}"
DERIVACION_ID="${DERIVACION_ID:-$inferred_derivacion_id}"

echo "fixture form_id=${FORM_ID:-<vacio>} hc_number=${HC_NUMBER:-<vacio>} derivacion_id=${DERIVACION_ID:-<vacio>}"

if [[ -z "${FORM_ID}" || -z "${HC_NUMBER}" ]]; then
  echo "[error] No se pudo obtener FORM_ID/HC_NUMBER. Define FORM_ID y HC_NUMBER en el env."
  exit 4
fi

scrap_status=$(
  curl -sS -o "${SCRAP_OUT}" -w "%{http_code}" \
    -X POST \
    -H "Cookie: ${COOKIE}" \
    --data-urlencode "form_id=${FORM_ID}" \
    --data-urlencode "hc_number=${HC_NUMBER}" \
    "${BASE_URL}${DERIVACIONES_BASE_PATH}/scrap"
)
echo "POST ${DERIVACIONES_BASE_PATH}/scrap -> ${scrap_status}"
head -c 500 "${SCRAP_OUT}" || true
echo

if [[ "${scrap_status}" != "200" && "${EXPECT_SCRAP_SUCCESS}" == "1" ]]; then
  echo "[error] scrap devolvio ${scrap_status} y EXPECT_SCRAP_SUCCESS=1."
  exit 10
fi

if [[ -z "${DERIVACION_ID}" ]]; then
  echo "[warn] No se encontro DERIVACION_ID en datatable. Saltando prueba de archivo."
  exit 0
fi

archivo_status=$(
  curl -sS -o "${PDF_OUT}" -w "%{http_code}" \
    -H "Cookie: ${COOKIE}" \
    "${BASE_URL}${DERIVACIONES_BASE_PATH}/archivo/${DERIVACION_ID}"
)
echo "GET ${DERIVACIONES_BASE_PATH}/archivo/${DERIVACION_ID} -> ${archivo_status}"

if [[ "${archivo_status}" == "200" ]]; then
  ls -lh "${PDF_OUT}" || true
else
  echo "[error] archivo devolvio ${archivo_status}"
  head -c 500 "${PDF_OUT}" || true
  echo
  exit 5
fi

echo "Done."
