"""
Utilidad para extraer los estimates desde la vista de Perfex CRM.

El script transforma la tabla HTML en un JSON estructurado que incluye:
- Resumen por estado (tarjetas superiores).
- Listado de cada estimate con enlaces y montos normalizados.
- Conteo de estimates por estado según la tabla.

Uso:
    python scrapping/perfex_estimates_scraper.py scrapping/sample_perfex_estimates.html --pretty
    cat pagina.html | python scrapping/perfex_estimates_scraper.py - --pretty
"""

import argparse
import json
import re
import sys
from collections import Counter
from pathlib import Path
from typing import Dict, List, Optional

from bs4 import BeautifulSoup


def _parse_percentage(text: str) -> Optional[float]:
    match = re.search(r"([-+]?\d+(?:\.\d+)?)", text)
    if not match:
        return None
    try:
        return float(match.group(1))
    except ValueError:
        return None


def _parse_fraction(text: str) -> Dict[str, Optional[int]]:
    match = re.search(r"(\d+)\s*/\s*(\d+)", text)
    if not match:
        return {"count": None, "total": None}

    count, total = match.groups()
    return {"count": int(count), "total": int(total)}


def _parse_amount(text: str) -> Optional[float]:
    cleaned = re.sub(r"[^0-9,.-]", "", text or "")
    if not cleaned:
        return None

    normalized = cleaned.replace(",", "")
    try:
        return float(normalized)
    except ValueError:
        return None


def _extract_status_summary(soup: BeautifulSoup) -> List[Dict[str, Optional[object]]]:
    summary = []
    for button in soup.select(".quick-top-stats button"):
        label = button.select_one(".tw-inline-flex")
        percentage = button.select_one(".tw-text-xs")
        counts = button.select_one(".tw-font-semibold")

        parsed_counts = _parse_fraction(counts.get_text(" ", strip=True) if counts else "")
        summary.append(
            {
                "label": label.get_text(" ", strip=True) if label else None,
                "percentage": _parse_percentage(percentage.get_text(" ", strip=True) if percentage else ""),
                "count": parsed_counts["count"],
                "total": parsed_counts["total"],
            }
        )
    return summary


def _extract_estimates(soup: BeautifulSoup) -> List[Dict[str, Optional[object]]]:
    estimates: List[Dict[str, Optional[object]]] = []

    for row in soup.select("table#estimates tbody tr"):
        cells = row.find_all("td")
        if len(cells) < 10:
            continue

        estimate_link = cells[0].find("a")
        customer_link = cells[3].find("a")
        project_link = cells[4].find("a")

        status_label = cells[9].find("span")
        estimates.append(
            {
                "estimate_number": estimate_link.get_text(strip=True) if estimate_link else cells[0].get_text(strip=True),
                "estimate_url": estimate_link["href"] if estimate_link and estimate_link.has_attr("href") else None,
                "amount": _parse_amount(cells[1].get_text(strip=True)),
                "total_tax": _parse_amount(cells[2].get_text(strip=True)),
                "customer": customer_link.get_text(strip=True) if customer_link else cells[3].get_text(strip=True),
                "customer_url": customer_link["href"] if customer_link and customer_link.has_attr("href") else None,
                "project": project_link.get_text(strip=True) if project_link else cells[4].get_text(strip=True),
                "project_url": project_link["href"] if project_link and project_link.has_attr("href") else None,
                "tags": [tag.get_text(strip=True) for tag in cells[5].select(".label")] if cells[5].find_all(
                    class_="label"
                ) else [],
                "date": cells[6].get_text(strip=True),
                "expiry_date": cells[7].get_text(strip=True),
                "reference": cells[8].get_text(strip=True),
                "status": status_label.get_text(strip=True) if status_label else cells[9].get_text(strip=True),
                "status_classes": status_label.get("class", []) if status_label else [],
            }
        )

    return estimates


def parse_perfex_estimates_page(html: str) -> Dict[str, object]:
    soup = BeautifulSoup(html, "html.parser")

    estimates = _extract_estimates(soup)
    status_counts = Counter([estimate["status"] for estimate in estimates if estimate["status"]])

    return {
        "summary": _extract_status_summary(soup),
        "estimates": estimates,
        "totals": {
            "estimates": len(estimates),
            "by_status": dict(status_counts),
        },
    }


def main(argv: List[str]) -> int:
    parser = argparse.ArgumentParser(description="Convierte la tabla HTML de Perfex CRM en un JSON con estimates.")
    parser.add_argument("source", help="Ruta al HTML a procesar o '-' para leer desde STDIN.")
    parser.add_argument(
        "--pretty",
        action="store_true",
        help="Imprimir el JSON con indentación para facilitar la lectura.",
    )
    args = parser.parse_args(argv)

    html = sys.stdin.read() if args.source == "-" else Path(args.source).read_text(encoding="utf-8")
    parsed = parse_perfex_estimates_page(html)
    print(json.dumps(parsed, ensure_ascii=False, indent=2 if args.pretty else None))
    return 0


if __name__ == "__main__":
    raise SystemExit(main(sys.argv[1:]))
