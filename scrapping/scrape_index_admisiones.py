from typing import Optional
import requests
from bs4 import BeautifulSoup
import sys
import json
import re
from urllib.parse import urlencode
import csv
from datetime import datetime, timedelta

# =========================
# Config
# =========================
modo_quieto = "--quiet" in sys.argv

USERNAME = "jdevera"
PASSWORD = "0925619736"
BASE = "https://cive.ddns.net:8085"
LOGIN_URL = f"{BASE}/site/login"

headers = {"User-Agent": "Mozilla/5.0"}


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

    d = start
    while d <= end:
        day_str = d.strftime("%Y-%m-%d")

        params = {
            "DocSolicitudProcedimientosAdmisionSearch[filtro]": "1",
            "DocSolicitudProcedimientosAdmisionSearch[fechaBusqueda]": day_str,
            "DocSolicitudProcedimientosAdmisionSearch[fechaBusquedaFin]": day_str,
            "_tog3d800b67": "all",
        }

        url = f"{BASE}/documentacion/doc-solicitud-procedimientos/index-admisiones?{urlencode(params)}"
        last_url = url

        try:
            r = session.get(url, headers=headers, timeout=60)
            if r.status_code != 200:
                raise RuntimeError(f"HTTP {r.status_code}")

            soup = BeautifulSoup(r.text, "html.parser")

            # Tabla principal (kv-grid-table)
            table = soup.select_one("#crud-datatable-admision table.kv-grid-table")
            if not table:
                # fallback: por si cambia el id
                table = soup.select_one("table.kv-grid-table")

            if not table:
                raise RuntimeError("No se encontró la tabla kv-grid-table (¿sesión expirada o cambió el HTML?)")

            tbody = table.find("tbody")
            if not tbody:
                raise RuntimeError("No se encontró <tbody>")

            rows = tbody.find_all("tr", recursive=False)

            current_group_date = ""

            for tr in rows:
                # Capturar fila agrupada (fecha)
                if "kv-grid-group-row" in (tr.get("class") or []):
                    td_date = tr.find("td")
                    current_group_date = clean_text(td_date)
                    continue

                cells = parse_row_cells_by_colseq(tr)
                if not cells:
                    continue

                # Helper: obtener texto por col-seq
                def c(k: int) -> str:
                    return cells.get(k, "")

                pedido_raw = c(6)  # Pedido
                pedido_id = re.search(r"\d+", pedido_raw).group(0) if re.search(r"\d+", pedido_raw) else ""

                # Fallback de fecha (encabezado agrupado vs columna oculta 5)
                raw_fecha = current_group_date or c(5) or day_str
                m_fecha = re.search(r"(\d{2}-\d{2}-\d{4})", raw_fecha)
                fecha_grupo = m_fecha.group(1) if m_fecha else raw_fecha

                data = {
                    "fecha_grupo": fecha_grupo,  # e.g. "19-12-2025"
                    "pedido_id": pedido_id,
                    "codigo_examen": c(8),

                    "hc_number": c(16),
                    "email": c(17),
                    "fecha_nac": c(18),
                    "sexo": c(20),
                    "ciudad": c(21),
                    "afiliacion": c(22),
                    "telefono": c(23),

                    # Atención
                    "procedimiento": c(24),
                    "doctor_agenda": c(25),
                    "cie10": c(30),
                    "estado_agenda": c(31),
                    "estado": c(33),

                    # Derivación
                    "codigo_derivacion": c(42),
                    "num_secuencial_derivacion": c(43),
                }

                paciente_full = c(13)

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

                data.update(split_nombre_latam(paciente_full))
                resultados.append(data)

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
