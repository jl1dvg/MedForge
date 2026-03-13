import argparse
import json
import os
import sys
from datetime import datetime
from typing import Dict, Iterable, List

import requests

from scrape_detalle_factura import scrape_detalle_factura


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description="Sincroniza detalle de facturacion real hacia MedForge.")
    parser.add_argument("--start", required=True, help="Mes inicio (YYYY-MM)")
    parser.add_argument("--end", required=True, help="Mes fin (YYYY-MM)")
    parser.add_argument("--api-url", default=os.getenv("MEDFORGE_API_URL", "https://asistentecive.consulmed.me"))
    parser.add_argument("--quiet", action="store_true")
    return parser.parse_args()


def parse_month(value: str) -> datetime:
    return datetime.strptime(value, "%Y-%m")


def month_iter(start: datetime, end: datetime) -> Iterable[str]:
    current = start.replace(day=1)
    last = end.replace(day=1)
    while current <= last:
        yield current.strftime("%Y-%m")
        if current.month == 12:
            current = current.replace(year=current.year + 1, month=1)
        else:
            current = current.replace(month=current.month + 1)


def chunked(items: List[Dict], size: int) -> Iterable[List[Dict]]:
    for idx in range(0, len(items), size):
        yield items[idx : idx + size]


def enviar_a_api(api_url: str, rows: List[Dict]) -> Dict:
    url = api_url.rstrip("/") + "/api/billing/guardar_facturacion_real.php"
    headers = {"Content-Type": "application/json", "Accept": "application/json"}
    response = requests.post(url, headers=headers, json=rows, timeout=120)
    if response.status_code not in {200, 207}:
        raise RuntimeError(f"Error al enviar a API: {response.status_code} - {response.text}")
    return response.json()


def sync_range(start: datetime, end: datetime, api_url: str) -> Dict:
    total_rows = 0
    sent_rows = 0
    batches = 0
    months = []

    for month_key in month_iter(start, end):
        scraped = scrape_detalle_factura(month_key)
        rows = scraped.get("rows", [])
        total_rows += len(rows)
        months.append(month_key)

        for batch in chunked(rows, 250):
            if not batch:
                continue
            enviar_a_api(api_url, batch)
            sent_rows += len(batch)
            batches += 1

    return {
        "status": "success",
        "from": start.strftime("%Y-%m"),
        "to": end.strftime("%Y-%m"),
        "months": months,
        "total_rows": total_rows,
        "sent_rows": sent_rows,
        "api_batches": batches,
    }


def main() -> int:
    args = parse_args()
    start = parse_month(args.start)
    end = parse_month(args.end)

    if start > end:
        print("El rango es invalido: inicio mayor que fin.", file=sys.stderr)
        return 2

    result = sync_range(start, end, args.api_url)

    if args.quiet:
        print(json.dumps(result, ensure_ascii=False))
    else:
        print(json.dumps(result, ensure_ascii=False, indent=2))

    return 0


if __name__ == "__main__":
    sys.exit(main())
