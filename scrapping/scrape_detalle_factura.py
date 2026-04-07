from __future__ import annotations

import csv
import json
import re
import sys
import unicodedata
from datetime import datetime
from typing import Dict, Iterable, List, Optional
from urllib.parse import urlencode, urljoin

import requests
from bs4 import BeautifulSoup

from scrape_index_admisiones import BASE, clean_text, headers, login

modo_quieto = "--quiet" in sys.argv
csv_output_path = None
if "--csv-out" in sys.argv:
    csv_output_index = sys.argv.index("--csv-out")
    if csv_output_index + 1 < len(sys.argv):
        csv_output_path = sys.argv[csv_output_index + 1]

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


def normalize_header(value: str) -> str:
    text = unicodedata.normalize("NFKD", (value or "").strip())
    text = "".join(ch for ch in text if not unicodedata.combining(ch))
    text = re.sub(r"\s+", " ", text)
    return text.lower()


def extract_export_headers(table) -> List[str]:
    header_rows = table.select("thead tr")
    best: List[str] = []
    best_score = -1

    for tr in header_rows:
        items: List[str] = []
        for cell in tr.find_all(["th", "td"], recursive=False):
            classes = cell.get("class") or []
            if "skip-export" in classes:
                continue

            clone = BeautifulSoup(str(cell), "html.parser").find(cell.name)
            if clone is not None:
                for tag in clone.select("input, select, option, button, textarea, script, style"):
                    tag.decompose()
                value = clean_text(clone)
            else:
                value = clean_text(cell)

            items.append(value)

        score = sum(1 for item in items if item != "")
        if len(items) > len(best) or (len(items) == len(best) and score > best_score):
            best = items
            best_score = score

    return best


def parse_export_row(tr) -> Optional[List[str]]:
    if "kv-grid-group-row" in (tr.get("class") or []):
        return None

    tds = tr.find_all("td", recursive=False)
    if not tds:
        return None

    if len(tds) == 1 and tds[0].has_attr("colspan"):
        return None

    values: List[str] = []
    for td in tds:
        classes = td.get("class") or []
        if "skip-export" in classes:
            continue
        values.append(clean_text(td))

    return values or None


def parse_row_cells_by_colseq(tr) -> Optional[Dict[int, str]]:
    if "kv-grid-group-row" in (tr.get("class") or []):
        return None

    tds = tr.find_all("td", recursive=False)
    if not tds:
        return None

    if len(tds) == 1 and tds[0].has_attr("colspan"):
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
    return cells


def build_colseq_title_map(table) -> Dict[int, str]:
    mapping: Dict[int, str] = {}
    for th in table.select("thead th[data-col-seq], thead td[data-col-seq]"):
        clone = BeautifulSoup(str(th), "html.parser").find(th.name)
        if clone is not None:
            for tag in clone.select("input, select, option, button, textarea, script, style"):
                tag.decompose()
            source = clone
        else:
            source = th

        col_seq = th.get("data-col-seq")
        try:
            key = int(col_seq)
        except Exception:
            continue

        title = normalize_header(clean_text(source))
        if title != "":
            mapping[key] = title
    return mapping


def find_colseq_by_title(col_map: Dict[int, str], candidates: List[str]) -> Optional[int]:
    normalized_candidates = [normalize_header(candidate) for candidate in candidates if candidate]

    for candidate in normalized_candidates:
        for seq, title in col_map.items():
            if title == candidate or title.startswith(candidate):
                return seq

    for candidate in normalized_candidates:
        for seq, title in col_map.items():
            if candidate in title:
                return seq

    return None


def build_header_positions(headers_row: List[str]) -> Dict[str, List[int]]:
    positions: Dict[str, List[int]] = {}
    for idx, header in enumerate(headers_row):
        positions.setdefault(normalize_header(header), []).append(idx)
    return positions


def get_value_by_candidates(
    values: List[str],
    header_positions: Dict[str, List[int]],
    candidates: List[str],
) -> str:
    for candidate in candidates:
        indexes = header_positions.get(normalize_header(candidate), [])
        for idx in indexes:
            if idx < len(values):
                return values[idx]
    return ""


def get_cell_value_by_candidates(
    cells: Dict[int, str],
    col_map: Dict[int, str],
    candidates: List[str],
) -> str:
    col_seq = find_colseq_by_title(col_map, candidates)
    if col_seq is None:
        return ""
    return cells.get(col_seq, "")


def parse_datetime(value: str) -> Optional[datetime]:
    raw = (value or "").strip()
    if raw == "":
        return None

    for fmt in ("%Y-%m-%d %H:%M:%S", "%Y-%m-%d"):
        try:
            return datetime.strptime(raw, fmt)
        except ValueError:
            continue

    return None


def parse_amount(value: str) -> float:
    raw = re.sub(r"[^\d,.\-]", "", (value or "").strip())
    if raw == "":
        return 0.0

    if "," in raw and "." in raw:
        if raw.rfind(",") > raw.rfind("."):
            raw = raw.replace(".", "").replace(",", ".")
        else:
            raw = raw.replace(",", "")
    elif "," in raw:
        raw = raw.replace(".", "").replace(",", ".")

    try:
        return round(float(raw), 4)
    except ValueError:
        return 0.0


def format_amount(value: float) -> str:
    return f"{value:.4f}"


def join_unique(values: Iterable[str], separator: str) -> str:
    seen = set()
    items: List[str] = []
    for value in values:
        normalized = re.sub(r"\s+", " ", (value or "").strip())
        if normalized == "":
            continue
        if normalized in seen:
            continue
        seen.add(normalized)
        items.append(normalized)
    return separator.join(items)


def first_nonempty(values: Iterable[str]) -> str:
    for value in values:
        normalized = re.sub(r"\s+", " ", (value or "").strip())
        if normalized != "":
            return normalized
    return ""


def latest_datetime_value(values: Iterable[str]) -> str:
    candidates = []
    for value in values:
        parsed = parse_datetime(value)
        if parsed is not None:
            candidates.append(parsed)
    if not candidates:
        return ""
    return max(candidates).strftime("%Y-%m-%d %H:%M:%S")


def split_codigo_procedimiento(value: str) -> tuple[str, str]:
    normalized = re.sub(r"\s+", " ", (value or "").strip())
    if normalized == "":
        return "", ""

    parts = [part.strip() for part in normalized.split("|", 1)]
    if len(parts) == 2 and re.fullmatch(r"\d+", parts[0]):
        return parts[0], normalized

    return "", normalized


def build_row_dict(headers_row: List[str], values_row: List[str]) -> Dict[str, str]:
    return {
        headers_row[idx]: values_row[idx] if idx < len(values_row) else ""
        for idx in range(len(headers_row))
    }


def export_rows_to_csv(headers_row: List[str], rows: List[Dict[str, str]], filename: str) -> None:
    with open(filename, "w", newline="", encoding="utf-8-sig") as f:
        writer = csv.writer(f)
        writer.writerow(headers_row)
        for row in rows:
            writer.writerow([row.get(header, "") for header in headers_row])


def find_next_page(soup: BeautifulSoup, current_url: str) -> Optional[str]:
    next_link = soup.select_one(".pagination li.next:not(.disabled) a[href], .pagination a[rel='next'][href]")
    if next_link is None:
        return None
    href = (next_link.get("href") or "").strip()
    if href == "" or href == "#":
        return None
    return urljoin(current_url, href)


def aggregate_rows(rows: List[Dict[str, str]], month_key: str) -> List[Dict[str, str]]:
    grouped: Dict[str, Dict[str, object]] = {}

    for row in rows:
        form_id = re.sub(r"\D+", "", row.get("form_id", ""))
        if form_id == "":
            continue

        group = grouped.setdefault(
            form_id,
            {
                "form_id": form_id,
                "detalle_factura_ids": [],
                "producto_ids": [],
                "codigos_producto": [],
                "factura_ids": [],
                "numero_facturas": [],
                "procedimientos": [],
                "realizado_por": [],
                "afiliacion": [],
                "paciente": [],
                "cliente": [],
                "fecha_agenda": [],
                "fecha_facturacion": [],
                "fecha_atencion": [],
                "formas_pago": [],
                "codigo_nota": [],
                "monto_honorario": 0.0,
                "monto_facturado": [],
                "area": [],
                "departamento_factura": [],
                "estado": [],
            },
        )

        codigo_producto, procedimiento = split_codigo_procedimiento(row.get("procedimiento", ""))
        if codigo_producto != "":
            group["codigos_producto"].append(codigo_producto)

        group["factura_ids"].append(row.get("factura_id", ""))
        group["numero_facturas"].append(row.get("numero_factura", ""))
        group["procedimientos"].append(procedimiento)
        group["realizado_por"].append(row.get("realizado_por", ""))
        group["afiliacion"].append(row.get("afiliacion", ""))
        group["paciente"].append(row.get("paciente", ""))
        group["cliente"].append(row.get("cliente", ""))
        group["fecha_agenda"].append(row.get("fecha_agenda", ""))
        group["fecha_facturacion"].append(row.get("fecha_facturacion", ""))
        group["fecha_atencion"].append(row.get("fecha_atencion", ""))
        group["formas_pago"].append(row.get("formas_pago", ""))
        group["codigo_nota"].append(row.get("codigo_nota", ""))
        group["monto_honorario"] += parse_amount(row.get("monto_honorario", ""))
        group["monto_facturado"].append(row.get("monto_facturado", ""))
        group["area"].append(row.get("area", ""))
        group["departamento_factura"].append(row.get("departamento_factura", ""))
        group["estado"].append(row.get("estado", ""))

    aggregated: List[Dict[str, str]] = []
    for form_id, group in grouped.items():
        procedimientos = join_unique(group["procedimientos"], " || ")
        realizado_por_values = join_unique(group["realizado_por"], " | ")

        aggregated.append(
            {
                "form_id": form_id,
                "detalle_factura_ids": join_unique(group["detalle_factura_ids"], " | "),
                "producto_ids": join_unique(group["producto_ids"], " | "),
                "codigos_producto": join_unique(group["codigos_producto"], " | "),
                "factura_id": first_nonempty(reversed(group["factura_ids"])),
                "numero_factura": first_nonempty(reversed(group["numero_facturas"])),
                "procedimiento": procedimientos,
                "realizado_por": realizado_por_values,
                "afiliacion": first_nonempty(group["afiliacion"]),
                "paciente": first_nonempty(group["paciente"]),
                "cliente": first_nonempty(group["cliente"]),
                "fecha_agenda": latest_datetime_value(group["fecha_agenda"]),
                "fecha_facturacion": latest_datetime_value(group["fecha_facturacion"]),
                "fecha_atencion": latest_datetime_value(group["fecha_atencion"]),
                "formas_pago": join_unique(group["formas_pago"], " | "),
                "codigo_nota": join_unique(group["codigo_nota"], " | "),
                "monto_honorario": format_amount(group["monto_honorario"]),
                "monto_facturado": first_nonempty(reversed(group["monto_facturado"])),
                "area": first_nonempty(reversed(group["area"])),
                "departamento_factura": first_nonempty(reversed(group["departamento_factura"])),
                "estado": first_nonempty(reversed(group["estado"])),
                "source_month": month_key,
            }
        )

    aggregated.sort(key=lambda row: ((row.get("fecha_agenda") or row.get("fecha_facturacion") or ""), row["form_id"]))
    return aggregated


def scrape_detalle_factura(month_key: str) -> Dict:
    session = requests.Session()
    if not login(session):
        raise RuntimeError("Fallo el login")

    params = {
        "ConvSolicitudProcedimientoDetalleFacturaReporteSearch[fechaInicio]": month_label_legacy(month_key),
        "_tog2c15c9e3": "all",
    }
    next_url = (
        f"{BASE}/convenios/conv-solicitud-procedimiento-detalle-factura/index-rep?"
        f"{urlencode(params)}"
    )

    headers_row: List[str] = []
    export_rows: List[Dict[str, str]] = []
    raw_rows: List[Dict[str, str]] = []
    last_url = next_url

    while next_url:
        response = session.get(next_url, headers=headers, timeout=90)
        if response.status_code != 200:
            raise RuntimeError(f"HTTP {response.status_code}")

        last_url = next_url
        soup = BeautifulSoup(response.text, "html.parser")
        table = soup.select_one("#crud-datatable-reporte table.kv-grid-table")
        if not table:
            table = soup.select_one("table.kv-grid-table")
        if not table:
            raise RuntimeError("No se encontro la tabla kv-grid-table del reporte de facturacion.")

        tbody = table.find("tbody")
        if not tbody:
            raise RuntimeError("No se encontro tbody en el reporte de facturacion.")

        if not headers_row:
            headers_row = extract_export_headers(table)
            if not headers_row:
                raise RuntimeError("No se pudieron extraer encabezados exportables del reporte de facturacion.")

        normalized_headers = [normalize_header(header) for header in headers_row]
        normalized_lookup = {header: idx for idx, header in enumerate(normalized_headers)}

        required_headers = {
            "pedido": "form_id",
            "procedimiento": "procedimiento",
            "realizado por": "realizado_por",
            "afiliacion": "afiliacion",
            "paciente": "paciente",
            "cliente": "cliente",
            "fecha agenda": "fecha_agenda",
            "fecha facturacion": "fecha_facturacion",
            "fecha atencion": "fecha_atencion",
            "numero factura": "numero_factura",
            "factura id": "factura_id",
            "formas pago": "formas_pago",
            "nc": "codigo_nota",
            "monto honorario": "monto_honorario",
            "monto facturado": "monto_facturado",
            "area": "area",
            "estado": "estado",
        }

        for tr in tbody.find_all("tr", recursive=False):
            values = parse_export_row(tr)
            if not values:
                continue

            row_dict = build_row_dict(headers_row, values)
            export_rows.append(row_dict)
            parsed: Dict[str, str] = {
                "detalle_factura_ids": "",
                "producto_ids": "",
                "codigos_producto": "",
                "departamento_factura": "",
                "source_month": month_key,
            }

            for header_name, field_name in required_headers.items():
                idx = normalized_lookup.get(normalize_header(header_name))
                parsed[field_name] = values[idx] if idx is not None and idx < len(values) else row_dict.get(header_name, "")

            parsed["form_id"] = re.sub(r"\D+", "", parsed.get("form_id", ""))
            if parsed["form_id"] == "":
                continue

            raw_rows.append(parsed)

        next_url = find_next_page(soup, next_url)

    rows = aggregate_rows(raw_rows, month_key)

    if csv_output_path:
        export_rows_to_csv(headers_row, export_rows, csv_output_path)

    return {
        "url": last_url,
        "month": month_key,
        "raw_total": len(export_rows),
        "total": len(rows),
        "rows": rows,
    }


if __name__ == "__main__":
    if len(sys.argv) < 2:
        print("Uso: python scrape_detalle_factura.py YYYY-MM [--quiet] [--csv-out archivo.csv]")
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
