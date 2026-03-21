#!/usr/bin/env bash
set -euo pipefail

PROJECT_DIR="${PROJECT_DIR:-/kunden/homepages/26/d793096920/htdocs/medforge}"
LOG_DIR="$PROJECT_DIR/storage/logs"
LOCK_FILE="/tmp/sync_index_admisiones_window.lock"
PHP_BIN="${PHP_BIN:-/usr/bin/php8.3-cli}"
SCRIPT="$PROJECT_DIR/scrapping/sync_index_admisiones.php"

LOCAL_PORT="${LOCAL_PORT:-13306}"
REMOTE_DB_HOST="${REMOTE_DB_HOST:-127.0.0.1}"
REMOTE_DB_PORT="${REMOTE_DB_PORT:-3306}"
SSH_HOST="${SSH_HOST:-190.110.204.74}"
SSH_PORT="${SSH_PORT:-3000}"
SSH_USER="${SSH_USER:-tecnico}"

mkdir -p "$LOG_DIR"
LOG_FILE="$LOG_DIR/sync_index_admisiones_window.log"

LOOKBACK_DAYS="${LOOKBACK_DAYS:-14}"
LOOKAHEAD_DAYS="${LOOKAHEAD_DAYS:-14}"

START_DATE="$(date -d "${LOOKBACK_DAYS} days ago" +%F)"
END_DATE="$(date -d "${LOOKAHEAD_DAYS} days" +%F)"

{
  echo "[$(date '+%Y-%m-%d %H:%M:%S')] [start] lookback=${LOOKBACK_DAYS} lookahead=${LOOKAHEAD_DAYS} start_date=${START_DATE} end_date=${END_DATE}"
} >> "$LOG_FILE"

if ! nc -z 127.0.0.1 "$LOCAL_PORT" >/dev/null 2>&1; then
  {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] [tunnel] opening ssh tunnel on 127.0.0.1:${LOCAL_PORT} -> ${REMOTE_DB_HOST}:${REMOTE_DB_PORT}"
  } >> "$LOG_FILE"
  ssh -f -N -p "$SSH_PORT" -L "${LOCAL_PORT}:${REMOTE_DB_HOST}:${REMOTE_DB_PORT}" "${SSH_USER}@${SSH_HOST}"
  sleep 2
fi

if /usr/bin/flock -n "$LOCK_FILE" env \
  SIGCENTER_DB_HOST=127.0.0.1 \
  SIGCENTER_DB_PORT="$LOCAL_PORT" \
  "$PHP_BIN" "$SCRIPT" \
  --start "$START_DATE" \
  --end "$END_DATE" \
  --quiet >> "$LOG_FILE" 2>&1; then
  {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] [done] completed successfully"
  } >> "$LOG_FILE"
else
  status=$?
  {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] [error] exit_code=${status}"
  } >> "$LOG_FILE"
  exit "$status"
fi
