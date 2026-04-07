from typing import Optional
import os
import requests
from bs4 import BeautifulSoup
import sys
import json
import re
from urllib.parse import urlencode, urljoin
import csv
from datetime import datetime, timedelta

# =========================
# Config
# =========================
modo_quieto = "--quiet" in sys.argv

USERNAME = os.getenv("SIGCENTER_SCRAPER_USERNAME", "agsuper")
PASSWORD = os.getenv("SIGCENTER_SCRAPER_PASSWORD", "123456")
BASE = os.getenv("SIGCENTER_SCRAPER_BASE_URL", "https://sigcenter.ddns.net:18093").rstrip("/")
LOGIN_URL = f"{BASE}/site/login"

headers = {"User-Agent": "Mozilla/5.0"}

EXPORT_HEADER_INDEX = {
    "fecha_grupo": 2,
    "pedido": 3,
    "precio": 4,
    "codigo_examen": 5,
    "prefactura": 6,
    "hora": 9,
    "paciente": 10,
    "identificacion": 11,
    "hc_number": 12,
    "email": 13,
    "fecha_nac": 14,
    "sexo": 16,
    "ciudad": 17,
    "afiliacion": 18,
    "telefono": 19,
    "procedimiento": 20,
    "doctor_agenda": 21,
    "agenda_dpto": 23,
    "cie10": 26,
    "estado_agenda": 27,
    "estado": 29,
    "referido_prefactura_por": 32,
    "especificar_referido_prefactura": 33,
    "codigo_derivacion": 38,
    "num_secuencial_derivacion": 39,
}


# =========================
# Helpers
# =========================
def obtener_csrf_token(html: str) -> Optional[str]:
    soup = BeautifulSoup(html, "html.parser")
    csrf = soup.find("input", {"name": "_csrf-frontend"})
    return csrf["value"] if csrf and csrf.has_attr("value") else None


def login(session: requests.Session) -> bool:
    r = session.get(LOGIN_URL, headers=headers, timeout=30)
    csrf = obtener_csrf_token(r.text)
    if not csrf:
        print("❌ No se pudo obtener CSRF token.")
        return False

    payload = {
        "_csrf-frontend": csrf,
        "LoginForm[username]": USERNAME,
        "LoginForm[password]": PASSWORD,
        "LoginForm[rememberMe]": "1",
    }
    r = session.post(LOGIN_URL, data=payload, headers=headers, timeout=30)
    # Heurística simple
    return "logout" in r.text.lower() or "/site/logout" in r.text.lower()


def clean_text(el) -> str:
    if not el:
        return ""
    return re.sub(r"\s+", " ", el.get_text(" ", strip=True)).strip()


def infer_sede_from_agenda_dpto(value: str) -> str:
    value = re.sub(r"\s+", " ", (value or "").strip()).upper()
    if not value:
        return ""

    pos_ceibos = value.rfind("CEIBOS")
    pos_matriz = value.rfind("MATRIZ")

    if pos_ceibos == -1 and pos_matriz == -1:
        return ""
    if pos_ceibos > pos_matriz:
        return "CEIBOS"
    return "MATRIZ"




def build_colseq_title_map(table) -> dict:
    mapping = {}
    for th in table.select('thead th[data-col-seq]'):
        th_clone = BeautifulSoup(str(th), "html.parser").find("th")
        if th_clone:
            for tag in th_clone.select("input, select, option, button, textarea"):
                tag.decompose()
            source = th_clone
        else:
            source = th
        col_seq = th.get('data-col-seq')
        try:
            k = int(col_seq)
        except Exception:
            continue
        title = clean_text(source).lower()
        if title:
            mapping[k] = title
    return mapping


def find_colseq_by_title(col_map: dict, candidates: list[str], fallback: Optional[int] = None) -> Optional[int]:
    if not col_map:
        return fallback
    normalized_candidates = [re.sub(r"\s+", " ", c.strip().lower()) for c in candidates if c and c.strip()]
    for candidate in normalized_candidates:
        for seq, title in col_map.items():
            normalized_title = re.sub(r"\s+", " ", title)
            if normalized_title == candidate or normalized_title.startswith(candidate):
                return seq
    for seq, title in col_map.items():
        normalized_title = re.sub(r"\s+", " ", title)
        for candidate in normalized_candidates:
            if candidate in normalized_title:
                return seq
    return fallback


def get_cell_value(
    cells: dict,
    col_map: dict,
    candidates: list[str],
    fallback: Optional[int] = None,
) -> str:
    col_seq = find_colseq_by_title(col_map, candidates, fallback=fallback)
    if col_seq is None:
        return ""
    return cells.get(col_seq, "")


def is_placeholder_value(value: str) -> bool:
    normalized = re.sub(r"\s+", " ", (value or "").strip()).upper()
    return normalized in {
        "",
        "-",
        "--",
        "SELECCIONE",
        "SELECCIONAR",
        "SELECT",
        "(NO DEFINIDO)",
        "NO DEFINIDO",
        "NO HAY DATOS",
        "NO HAY DATO",
        "N/A",
        "NULL",
    }


def infer_hc_from_row(cells: dict, pedido_id: str) -> str:
    for key in sorted(cells.keys()):
        value = re.sub(r"\s+", " ", str(cells.get(key, "")).strip())
        if not value:
            continue
        for match in re.findall(r"\b\d{8,15}\b", value):
            if pedido_id and match == pedido_id:
                continue
            if len(match) >= 10:
                return match
    return ""

def parse_row_cells_by_colseq(tr):
    """Devuelve dict {col_seq:int -> text:str} para una fila de datos.
    Ignora filas agrupadas (fecha) y filas sin celdas."""
    # Fila agrupada (fecha)
    if "kv-grid-group-row" in (tr.get("class") or []):
        return None

    tds = tr.find_all("td", recursive=False)
    if not tds:
        return None

    # Algunas filas especiales pueden venir con un solo td con colspan
    if len(tds) == 1 and tds[0].has_attr("colspan"):
        return None

    cells = {}
    for td in tds:
        col_seq = td.get("data-col-seq")
        if col_seq is None:
            continue
        try:
            k = int(col_seq)
        except Exception:
            continue
        cells[k] = clean_text(td)
    return cells


def parse_row_values(tr):
    tds = tr.find_all("td", recursive=False)
    if not tds:
        return None
    if len(tds) == 1 and tds[0].has_attr("colspan"):
        return None
    return [clean_text(td) for td in tds]


def normalize_header_text(value: str) -> str:
    value = re.sub(r"\s+", " ", (value or "").strip())
    return value.lower()


def extract_csv_headers(table) -> list[str]:
    head_rows = table.select("thead tr")
    if not head_rows:
        return []
    headers = []
    for cell in head_rows[0].find_all(["th", "td"], recursive=False):
        classes = cell.get("class") or []
        if "skip-export" in classes:
            continue
        headers.append(clean_text(cell))
    return headers


def parse_csv_row_values(tr):
    tds = tr.find_all("td", recursive=False)
    if not tds:
        return None
    if len(tds) == 1 and tds[0].has_attr("colspan"):
        return None
    values = []
    for td in tds:
        classes = td.get("class") or []
        if "skip-export" in classes:
            continue
        values.append(clean_text(td))
    return values


def extract_export_headers(table) -> list[str]:
    header_rows = table.select("thead tr")
    best = []
    best_score = -1
    for tr in header_rows:
        items = []
        for cell in tr.find_all(["th", "td"], recursive=False):
            cell_clone = BeautifulSoup(str(cell), "html.parser").find(cell.name)
            if cell_clone:
                for tag in cell_clone.select("input, select, option, button, textarea, script, style"):
                    tag.decompose()
                items.append(clean_text(cell_clone))
            else:
                items.append(clean_text(cell))
        score = sum(1 for item in items if item != "")
        if len(items) > len(best) or (len(items) == len(best) and score > best_score):
            best = items
            best_score = score
    return best


def build_header_lookup(headers: list[str], values: list[str]) -> dict[str, str]:
    if not headers:
        return {}

    lookup = {}
    blank_count = 0
    total = max(len(headers), len(values))
    for idx in range(total):
        raw_header = headers[idx] if idx < len(headers) else ""
        raw_value = values[idx] if idx < len(values) else ""
        key = normalize_header_text(raw_header)
        if key == "":
            blank_count += 1
            key = f"__blank__{blank_count}"
        lookup[key] = raw_value
    return lookup


def get_lookup_value(lookup: dict[str, str], candidates: list[str], default: str = "") -> str:
    for candidate in candidates:
        key = normalize_header_text(candidate)
        if key in lookup:
            return lookup[key]
    return default


def build_header_positions(headers: list[str]) -> dict[str, list[int]]:
    positions = {}
    for idx, header in enumerate(headers):
        key = normalize_header_text(header)
        positions.setdefault(key, []).append(idx)
    return positions


def find_header_indexes(header_positions: dict[str, list[int]], candidates: list[str]) -> list[int]:
    for candidate in candidates:
        key = normalize_header_text(candidate)
        if key in header_positions:
            return header_positions[key]
    return []


def get_header_value(
    header_positions: dict[str, list[int]],
    values: list[str],
    candidates: list[str],
) -> tuple[bool, str]:
    indexes = find_header_indexes(header_positions, candidates)
    if not indexes:
        return False, ""
    for idx in indexes:
        if idx < len(values):
            return True, values[idx]
    return True, ""


def normalize_hc_value(value: str) -> str:
    value = re.sub(r"\s+", " ", (value or "").strip())
    if is_placeholder_value(value):
        return ""

    digits = re.findall(r"\d{8,15}", value)
    if digits:
        return digits[0]
    return ""


def normalize_sexo_value(value: str) -> str:
    value = re.sub(r"\s+", " ", (value or "").strip()).upper()
    if value in {"M", "MASCULINO"} or value.startswith("MASC"):
        return "M"
    if value in {"F", "FEMENINO"} or value.startswith("FEM"):
        return "F"
    return ""


# =========================
# Main scrape
# =========================
def scrape_index_admisiones(fecha_inicio: str, fecha_fin: str):
    session = requests.Session()
    if not login(session):
        raise RuntimeError("Fallo el login")

    def parse_yyyy_mm_dd(s: str):
        return datetime.strptime(s, "%Y-%m-%d").date()

    start = parse_yyyy_mm_dd(fecha_inicio)
    end = parse_yyyy_mm_dd(fecha_fin)
    if end < start:
        raise RuntimeError("Rango de fechas inválido: fecha_fin es menor que fecha_inicio")

    resultados = []
    errors = []
    last_url = ""

    seen_ids = set()

    d = start
    while d <= end:
        day_str = d.strftime("%Y-%m-%d")

        params = {
            "DocSolicitudProcedimientosAdmisionSearch[filtro]": "1",
            "DocSolicitudProcedimientosAdmisionSearch[fechaBusqueda]": day_str,
            "DocSolicitudProcedimientosAdmisionSearch[fechaBusquedaFin]": day_str,
            "_tog3d800b67": "all",
            "recalcular": "1",
        }

        try:
            next_url = f"{BASE}/documentacion/doc-solicitud-procedimientos/index-admisiones?{urlencode(params)}"
            current_group_date = ""

            while next_url:
                url = next_url
                last_url = url
                r = session.get(url, headers=headers, timeout=60)
                if r.status_code != 200:
                    raise RuntimeError(f"HTTP {r.status_code}")

                soup = BeautifulSoup(r.text, "html.parser")

                table = soup.select_one("#crud-datatable-admision table.kv-grid-table")
                if not table:
                    table = soup.select_one("table.kv-grid-table")

                if not table:
                    raise RuntimeError("No se encontró la tabla kv-grid-table (¿sesión expirada o cambió el HTML?)")

                tbody = table.find("tbody")
                if not tbody:
                    raise RuntimeError("No se encontró <tbody>")

                rows = tbody.find_all("tr", recursive=False)
                export_headers = extract_csv_headers(table)
                export_header_positions = build_header_positions(export_headers)
                col_title_map = build_colseq_title_map(table)
                col_referido_prefactura_por = find_colseq_by_title(
                    col_title_map,
                    ["referido prefactura por", "prefactura por", "referido por"],
                    fallback=44,
                )
                col_especificar_referido_prefactura = find_colseq_by_title(
                    col_title_map,
                    ["especificar referido prefactura", "especificar referido", "detalle referido prefactura"],
                    fallback=45,
                )

                for tr in rows:
                    if "kv-grid-group-row" in (tr.get("class") or []):
                        td_date = tr.find("td")
                        current_group_date = clean_text(td_date)
                        continue

                    values = parse_csv_row_values(tr)
                    cells = parse_row_cells_by_colseq(tr)
                    if not cells or not values:
                        continue

                    def c(k: int) -> str:
                        return cells.get(k, "")

                    def v(name: str, default: str = "") -> str:
                        lookup_candidates = {
                            "fecha_grupo": ["fecha", "lunes, 06-04-2026"],
                            "pedido": ["pedido"],
                            "precio": ["precio"],
                            "codigo_examen": ["código examen", "codigo examen"],
                            "prefactura": ["prefactura"],
                            "hora": ["hora"],
                            "paciente": ["paciente"],
                            "identificacion": ["identificación", "identificacion"],
                            "hc_number": ["# historia", "historia clínica", "historia clinica", "hc"],
                            "email": ["email"],
                            "fecha_nac": ["fecha nac", "fecha nacimiento"],
                            "sexo": ["género", "genero", "sexo"],
                            "ciudad": ["ciudad"],
                            "afiliacion": ["afiliación", "afiliacion"],
                            "telefono": ["teléfono", "telefono"],
                            "procedimiento": ["procedimiento"],
                            "doctor_agenda": ["doctor de agenda", "doctor"],
                            "agenda_dpto": ["agenda dpto.", "agenda dpto", "dpto agenda"],
                            "cie10": ["cie10", "cie 10"],
                            "estado_agenda": ["estado agenda"],
                            "estado": ["estado"],
                            "referido_prefactura_por": ["referido prefactura por"],
                            "especificar_referido_prefactura": ["especificar referido prefactura"],
                            "codigo_derivacion": ["código de derivación", "codigo de derivacion"],
                            "num_secuencial_derivacion": ["número secuencial de derivación", "numero secuencial de derivacion"],
                        }
                        found, from_lookup = get_header_value(
                            export_header_positions,
                            values,
                            lookup_candidates.get(name, []),
                        )
                        if found:
                            return from_lookup
                        idx = EXPORT_HEADER_INDEX.get(name)
                        if idx is None:
                            return default
                        return values[idx] if idx < len(values) else default

                    pedido_raw = v("pedido", c(6))
                    pedido_id = re.search(r"\d+", pedido_raw).group(0) if re.search(r"\d+", pedido_raw) else ""
                    if pedido_id and pedido_id in seen_ids:
                        continue

                    raw_fecha = current_group_date or v("fecha_grupo", "") or c(5) or day_str
                    m_fecha = re.search(r"(\d{2}-\d{2}-\d{4})", raw_fecha)
                    fecha_grupo = m_fecha.group(1) if m_fecha else raw_fecha
                    codigo_examen = v("codigo_examen", get_cell_value(cells, col_title_map, ["código examen", "codigo examen"], fallback=8))
                    prefactura = v("prefactura", get_cell_value(cells, col_title_map, ["prefactura", "nro oda", "oda"], fallback=9))
                    hora = v("hora", get_cell_value(cells, col_title_map, ["hora"], fallback=11))
                    paciente_full = v("paciente", get_cell_value(cells, col_title_map, ["paciente"], fallback=13))
                    hc_number = normalize_hc_value(v("hc_number", get_cell_value(
                        cells,
                        col_title_map,
                        ["# historia", "no historia", "historia clínica", "historia clinica", "historia", "hc"],
                        fallback=16
                    )))
                    if hc_number == "":
                        hc_number = normalize_hc_value(v("identificacion", ""))
                    if hc_number == "":
                        hc_number = infer_hc_from_row(cells, pedido_id)
                    email = v("email", get_cell_value(cells, col_title_map, ["email", "correo"], fallback=17))
                    fecha_nac = v("fecha_nac", get_cell_value(cells, col_title_map, ["fecha nac", "fecha nacimiento"], fallback=18))
                    sexo = normalize_sexo_value(v("sexo", get_cell_value(cells, col_title_map, ["género", "genero", "sexo"], fallback=20)))
                    ciudad = v("ciudad", get_cell_value(cells, col_title_map, ["ciudad"], fallback=21))
                    afiliacion = v("afiliacion", get_cell_value(cells, col_title_map, ["afiliación", "afiliacion"], fallback=22))
                    telefono = v("telefono", get_cell_value(cells, col_title_map, ["teléfono", "telefono"], fallback=23))
                    procedimiento = v("procedimiento", get_cell_value(cells, col_title_map, ["procedimiento"], fallback=24))
                    doctor_agenda = v("doctor_agenda", get_cell_value(cells, col_title_map, ["doctor de agenda", "doctor"], fallback=25))
                    agenda_dpto = v("agenda_dpto", get_cell_value(cells, col_title_map, ["agenda dpto", "agenda dpto.", "dpto agenda"], fallback=27))
                    sede_departamento = infer_sede_from_agenda_dpto(agenda_dpto)
                    cie10 = v("cie10", get_cell_value(cells, col_title_map, ["cie10", "cie 10", "diagnóstico", "diagnostico"], fallback=30))
                    estado_agenda = v("estado_agenda", get_cell_value(cells, col_title_map, ["estado agenda"], fallback=31))
                    estado = v("estado", get_cell_value(cells, col_title_map, ["estado"], fallback=33))
                    if is_placeholder_value(estado):
                        estado = ""

                    fecha_evento = ""
                    if hora:
                        try:
                            fecha_evento = datetime.strptime(
                                f"{fecha_grupo} {hora}",
                                "%d-%m-%Y %H:%M:%S",
                            ).strftime("%Y-%m-%d %H:%M:%S")
                        except ValueError:
                            try:
                                fecha_evento = datetime.strptime(
                                    f"{fecha_grupo} {hora}",
                                    "%d-%m-%Y %H:%M",
                                ).strftime("%Y-%m-%d %H:%M:%S")
                            except ValueError:
                                fecha_evento = ""

                    data = {
                        "fecha_grupo": fecha_grupo,
                        "fecha_evento": fecha_evento,
                        "hora": hora,
                        "pedido_id": pedido_id,
                        "precio": v("precio", c(7)),
                        "codigo_examen": codigo_examen,
                        "prefactura": prefactura,
                        "hc_number": hc_number,
                        "email": email,
                        "fecha_nac": fecha_nac,
                        "sexo": sexo,
                        "ciudad": ciudad,
                        "afiliacion": afiliacion,
                        "telefono": telefono,
                        "procedimiento": procedimiento,
                        "doctor_agenda": doctor_agenda,
                        "agenda_dpto": agenda_dpto,
                        "sede_departamento": sede_departamento,
                        "cie10": cie10,
                        "estado_agenda": estado_agenda,
                        "estado": estado,
                        "referido_prefactura_por": v("referido_prefactura_por", c(col_referido_prefactura_por) if col_referido_prefactura_por is not None else ""),
                        "especificar_referido_prefactura": v("especificar_referido_prefactura", c(col_especificar_referido_prefactura) if col_especificar_referido_prefactura is not None else ""),
                        "codigo_derivacion": v("codigo_derivacion", c(42)),
                        "num_secuencial_derivacion": v("num_secuencial_derivacion", c(43)),
                        "paciente_full": paciente_full,
                        "nombre_completo": paciente_full,
                    }

                    def split_nombre_latam(full: str):
                        full = re.sub(r"\s+", " ", (full or "").strip())
                        if not full:
                            return {"lname": "", "lname2": "", "fname": "", "mname": ""}

                        tokens = full.split(" ")

                        particles_2 = {"DE", "DEL"}
                        particles_3 = {("DE", "LA"), ("DE", "LAS"), ("DE", "LOS")}

                        def take_surname_at(i: int):
                            if i >= len(tokens):
                                return "", i

                            if i + 2 < len(tokens) and (tokens[i], tokens[i + 1]) in particles_3:
                                return f"{tokens[i]} {tokens[i + 1]} {tokens[i + 2]}", i + 3

                            if i + 1 < len(tokens) and tokens[i] in particles_2:
                                return f"{tokens[i]} {tokens[i + 1]}", i + 2

                            return tokens[i], i + 1

                        if len(tokens) == 1:
                            return {"lname": tokens[0], "lname2": "", "fname": "", "mname": ""}
                        if len(tokens) == 2:
                            return {"lname": tokens[0], "lname2": "", "fname": tokens[1], "mname": ""}

                        lname, idx = take_surname_at(0)
                        lname2, idx = take_surname_at(idx)

                        nombres = tokens[idx:]
                        fname = nombres[0] if len(nombres) > 0 else ""
                        mname = " ".join(nombres[1:]) if len(nombres) > 1 else ""

                        return {"lname": lname, "lname2": lname2, "fname": fname, "mname": mname}

                    split_name = split_nombre_latam(paciente_full)
                    data.update(split_name)
                    data["apellidos"] = " ".join(part for part in [split_name["lname"], split_name["lname2"]] if part).strip()
                    data["nombres"] = " ".join(part for part in [split_name["fname"], split_name["mname"]] if part).strip()
                    resultados.append(data)
                    if pedido_id:
                        seen_ids.add(pedido_id)

                next_url = None
                next_link = soup.select_one(".pagination li.next:not(.disabled) a[href], .pagination a[rel='next'][href]")
                if next_link and next_link.get("href"):
                    next_url = urljoin(url, next_link["href"])

        except Exception as ex:
            errors.append({"date": day_str, "error": str(ex), "url": url})

        d = d + timedelta(days=1)

    if not resultados and errors:
        # Si no se obtuvo nada, devolvemos un error explícito con el primer fallo
        first = errors[0]
        raise RuntimeError(f"Scrape falló. Ejemplo: {first['date']} -> {first['error']}")

    # En rango, devolvemos la última URL consultada (por consistencia)
    url = last_url

    return {
        "url": url,
        "fecha_inicio": fecha_inicio,
        "fecha_fin": fecha_fin,
        "total": len(resultados),
        "rows": resultados,
    }


def export_to_csv(rows, filename):
    if not rows:
        return
    fieldnames = rows[0].keys()
    with open(filename, "w", newline="", encoding="utf-8") as f:
        writer = csv.DictWriter(f, fieldnames=fieldnames)
        writer.writeheader()
        for row in rows:
            writer.writerow(row)


# =========================
# CLI
# =========================
if __name__ == "__main__":
    if len(sys.argv) < 3:
        print("Uso: python scrape_index_admisiones.py YYYY-MM-DD YYYY-MM-DD [--quiet]")
        sys.exit(1)

    fecha_inicio = sys.argv[1]
    fecha_fin = sys.argv[2]

    csv_file = None
    if "--csv" in sys.argv:
        idx = sys.argv.index("--csv")
        if idx + 1 < len(sys.argv):
            csv_file = sys.argv[idx + 1]
        else:
            csv_file = f"index_admisiones_{fecha_inicio}_{fecha_fin}.csv"

    try:
        out = scrape_index_admisiones(fecha_inicio, fecha_fin)
        if csv_file:
            export_to_csv(out["rows"], csv_file)
        if modo_quieto:
            print(json.dumps(out, ensure_ascii=False))
        else:
            print(f"✅ OK. Registros: {out['total']}")
            print("📌 URL:", out["url"])
            print(json.dumps(out["rows"], ensure_ascii=False, indent=2))
    except Exception as e:
        print("❌ Error:", str(e))
        sys.exit(2)
