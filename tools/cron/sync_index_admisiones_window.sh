#!/usr/bin/env bash
set -euo pipefail

PROJECT_DIR="/root/.openclaw/workspace/MedForge"
VENV_PY="$PROJECT_DIR/venv/bin/python"
SCRIPT="$PROJECT_DIR/scrapping/sync_index_admisiones.py"
LOG_DIR="$PROJECT_DIR/storage/logs"
LOCK_FILE="/tmp/sync_index_admisiones_window.lock"

mkdir -p "$LOG_DIR"

# Rango dinámico: hoy -14 días / hoy +14 días
START_DATE="$(date -d '14 days ago' +%F)"
END_DATE="$(date -d '14 days' +%F)"

/usr/bin/flock -n "$LOCK_FILE" "$VENV_PY" "$SCRIPT" \
  --start "$START_DATE" \
  --end "$END_DATE" \
  --quiet >> "$LOG_DIR/sync_index_admisiones_window.log" 2>&1
