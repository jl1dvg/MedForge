#!/usr/bin/env python3
"""Rutina de sincronización IA ejecutada desde el cron de MedForge."""
from __future__ import annotations

import json
import os
from datetime import datetime
from pathlib import Path


def main() -> None:
    base_dir = Path(__file__).resolve().parent.parent
    logs_dir = base_dir / "storage" / "logs"
    logs_dir.mkdir(parents=True, exist_ok=True)

    timestamp = datetime.now().isoformat(timespec="seconds")
    log_path = logs_dir / "ai_batch.log"

    with log_path.open("a", encoding="utf-8") as handler:
        handler.write(f"[{timestamp}] Sincronización IA ejecutada desde cron\n")

    payload = {
        "success": True,
        "timestamp": timestamp,
        "log_file": str(log_path),
        "cwd": os.getcwd(),
    }

    stdout = os.fdopen(1, "w", encoding="utf-8")
    json.dump(payload, fp=stdout)
    stdout.write("\n")


if __name__ == "__main__":
    main()
