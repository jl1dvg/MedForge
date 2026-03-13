from __future__ import annotations

import json
import re
import sys
from datetime import datetime
from typing import Dict, List
from urllib.parse import urlencode

import requests
from bs4 import BeautifulSoup

from scrape_index_admisiones import BASE, clean_text, headers, login

modo_quieto = "--quiet" in sys.argv

MONTH_LABELS = {
    1: "Enero",
    2: "Febrero",
    3: "Marzo",
    4: "Abril",
    5: "Mayo",
    6: "Junio",
    7: "Julio",
    8: "Agosto",
    9: "Septiembre",
    10: "Octubre",
    11: "Noviembre",
    12: "Diciembre",
}


def month_label_legacy(month_key: str) -> str:
    try:
        dt = datetime.strptime(month_key, "%Y-%m")
    except ValueError as exc:
        raise RuntimeError("Mes invalido. Usa YYYY-MM.") from exc

    return f"{MONTH_LABELS[dt.month]}-{dt.year}"


def parse_row_cells_by_colseq(tr) -> Dict[int, str] | None:
    tds = tr.find_all("td", recursive=False)
    if not tds:
        return None

    cells: Dict[int, str] = {}
    for td in tds:
        col_seq = td.get("data-col-seq")
        if col_seq is None:
            continue
        try:
            key = int(col_seq)
        except Exception:
            continue
        cells[key] = clean_text(td)

    return cells or None


def scrape_detalle_factura(month_key: str) -> Dict:
    session = requests.Session()
    if not login(session):
        raise RuntimeError("Fallo el login")

    params = {
        "ConvSolicitudProcedimientoDetalleFacturaReporteSearch[fechaInicio]": month_label_legacy(month_key),
        "_tog2c15c9e3": "all",
    }
    url = (
        f"{BASE}/convenios/conv-solicitud-procedimiento-detalle-factura/index-rep?"
        f"{urlencode(params)}"
    )

    response = session.get(url, headers=headers, timeout=90)
    if response.status_code != 200:
        raise RuntimeError(f"HTTP {response.status_code}")

    soup = BeautifulSoup(response.text, "html.parser")
    table = soup.select_one("#crud-datatable-reporte table.kv-grid-table")
    if not table:
        table = soup.select_one("table.kv-grid-table")
    if not table:
        raise RuntimeError("No se encontro la tabla kv-grid-table del reporte de facturacion.")

    tbody = table.find("tbody")
    if not tbody:
        raise RuntimeError("No se encontro tbody en el reporte de facturacion.")

    rows: List[Dict[str, str]] = []
    for tr in tbody.find_all("tr", recursive=False):
        cells = parse_row_cells_by_colseq(tr)
        if not cells:
            continue

        def c(k: int) -> str:
            return cells.get(k, "")

        form_id = re.sub(r"\D+", "", c(5))
        if not form_id:
            continue

        rows.append(
            {
                "form_id": form_id,
                "procedimiento": c(2),
                "realizado_por": c(3),
                "afiliacion": c(4),
                "paciente": c(6),
                "cliente": c(7),
                "fecha_agenda": c(8),
                "fecha_facturacion": c(9),
                "fecha_atencion": c(10),
                "numero_factura": c(11),
                "factura_id": c(12),
                "formas_pago": c(13),
                "codigo_nota": c(14),
                "monto_honorario": c(15),
                "monto_facturado": c(16),
                "area": c(17),
                "estado": c(18),
                "source_month": month_key,
            }
        )

    return {
        "url": url,
        "month": month_key,
        "total": len(rows),
        "rows": rows,
    }


if __name__ == "__main__":
    if len(sys.argv) < 2:
        print("Uso: python scrape_detalle_factura.py YYYY-MM [--quiet]")
        sys.exit(1)

    month = sys.argv[1]

    try:
        out = scrape_detalle_factura(month)
        if modo_quieto:
            print(json.dumps(out, ensure_ascii=False))
        else:
            print(f"OK. Registros: {out['total']}")
            print("URL:", out["url"])
            print(json.dumps(out["rows"][:20], ensure_ascii=False, indent=2))
    except Exception as exc:
        print("Error:", str(exc))
        sys.exit(2)
