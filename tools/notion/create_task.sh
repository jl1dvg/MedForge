#!/usr/bin/env bash
set -euo pipefail

# Carga variables desde archivo local (opcional)
if [[ -f "$HOME/.config/medforge/notion.env" ]]; then
  # shellcheck disable=SC1090
  source "$HOME/.config/medforge/notion.env"
fi

exec "$(dirname "$0")/create_task.mjs" "$@"
