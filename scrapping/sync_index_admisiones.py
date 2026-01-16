import argparse
from datetime import datetime, timedelta
import json
import os
import sys
from typing import Dict, Iterable, List, Optional

import requests

from scrape_index_admisiones import scrape_index_admisiones


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description="Sincroniza index-admisiones hacia MedForge.")
    parser.add_argument("--start", required=True, help="Fecha inicio (YYYY-MM-DD)")
    parser.add_argument("--end", required=True, help="Fecha fin (YYYY-MM-DD)")
    parser.add_argument("--api-url", default=os.getenv("MEDFORGE_API_URL", "https://asistentecive.consulmed.me"))
    parser.add_argument("--quiet", action="store_true")
    return parser.parse_args()


def parse_date(value: str) -> datetime:
    return datetime.strptime(value, "%Y-%m-%d")


def normalize_fecha_grupo(value: str) -> Optional[str]:
    value = (value or "").strip()
    if not value:
        return None
    try:
        return datetime.strptime(value, "%d-%m-%Y").strftime("%Y-%m-%d")
    except ValueError:
        return None


def normalize_iso_date(value: str) -> Optional[str]:
    value = (value or "").strip()
    if not value:
        return None
    try:
        return datetime.strptime(value, "%Y-%m-%d").strftime("%Y-%m-%d")
    except ValueError:
        return None


def chunked(items: List[Dict], size: int) -> Iterable[List[Dict]]:
    for idx in range(0, len(items), size):
        yield items[idx : idx + size]


def build_payload(row: Dict) -> Dict:
    payload = {
        "hcNumber": row.get("hc_number", "").strip(),
        "form_id": row.get("pedido_id", "").strip(),
        "procedimiento_proyectado": row.get("procedimiento", "").strip(),
        "doctor": row.get("doctor_agenda", "").strip(),
        "cie10": row.get("cie10", "").strip(),
        "estado_agenda": row.get("estado_agenda", "").strip(),
        "estado": row.get("estado", "").strip(),
        "codigo_derivacion": row.get("codigo_derivacion", "").strip(),
        "num_secuencial_derivacion": row.get("num_secuencial_derivacion", "").strip(),
        "fname": row.get("fname", "").strip(),
        "mname": row.get("mname", "").strip(),
        "lname": row.get("lname", "").strip(),
        "lname2": row.get("lname2", "").strip(),
        "email": row.get("email", "").strip(),
        "fecha_nacimiento": normalize_iso_date(row.get("fecha_nac", "")),
        "sexo": row.get("sexo", "").strip(),
        "ciudad": row.get("ciudad", "").strip(),
        "afiliacion": row.get("afiliacion", "").strip(),
        "telefono": row.get("telefono", "").strip(),
    }

    fecha = normalize_fecha_grupo(row.get("fecha_grupo", ""))
    if fecha:
        payload["fecha"] = fecha

    return {k: v for k, v in payload.items() if v not in (None, "")}


def enviar_a_api(api_url: str, datos: List[Dict]) -> Dict:
    url = api_url.rstrip("/") + "/api/proyecciones/guardar_index_admisiones.php"
    headers = {"Content-Type": "application/json", "Accept": "application/json"}
    respuesta = requests.post(url, headers=headers, json=datos, timeout=120)
    if respuesta.status_code != 200:
        raise RuntimeError(f"Error al enviar a API: {respuesta.status_code} - {respuesta.text}")
    return respuesta.json()


def sync_range(start: datetime, end: datetime, api_url: str) -> Dict:
    total_rows = 0
    sent_rows = 0
    skipped_rows = 0
    api_results = []

    current = start
    while current <= end:
        fecha = current.strftime("%Y-%m-%d")
        out = scrape_index_admisiones(fecha, fecha)
        rows = out.get("rows", [])
        total_rows += len(rows)

        payloads = []
        for row in rows:
            payload = build_payload(row)
            if not payload.get("hcNumber") or not payload.get("form_id") or not payload.get("procedimiento_proyectado"):
                skipped_rows += 1
                continue
            payloads.append(payload)

        for batch in chunked(payloads, 200):
            if not batch:
                continue
            result = enviar_a_api(api_url, batch)
            api_results.append(result)
            sent_rows += len(batch)

        current += timedelta(days=1)

    return {
        "status": "success",
        "from": start.strftime("%Y-%m-%d"),
        "to": end.strftime("%Y-%m-%d"),
        "total_rows": total_rows,
        "sent_rows": sent_rows,
        "skipped_rows": skipped_rows,
        "api_batches": len(api_results),
    }


def main() -> int:
    args = parse_args()
    start = parse_date(args.start)
    end = parse_date(args.end)

    if start > end:
        print("❌ El rango es inválido: inicio mayor que fin.", file=sys.stderr)
        return 2

    result = sync_range(start, end, args.api_url)

    if args.quiet:
        print(json.dumps(result, ensure_ascii=False))
    else:
        print(json.dumps(result, ensure_ascii=False, indent=2))

    return 0


if __name__ == "__main__":
    sys.exit(main())
