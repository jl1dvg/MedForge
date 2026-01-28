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

                pedido_raw = c(6)  # Pedido (puede venir como '230395/229444' o '229445/ADMISIÓN')
                pedido_raw_norm = re.sub(r"\s+", " ", (pedido_raw or "").strip())

                # Tomamos el número ANTES de la barra como pedido_id
                pedido_id = ""
                pedido_origen_raw = ""
                pedido_origen_id = ""
                pedido_origen_tipo = ""  # 'pedido' | 'departamento' | ''

                if "/" in pedido_raw_norm:
                    left, right = pedido_raw_norm.split("/", 1)
                    m_left = re.search(r"\d+", left)
                    pedido_id = m_left.group(0) if m_left else ""

                    pedido_origen_raw = right.strip()
                    m_right = re.search(r"\d+", pedido_origen_raw)
                    if m_right:
                        pedido_origen_id = m_right.group(0)
                        pedido_origen_tipo = "pedido"
                    elif pedido_origen_raw:
                        pedido_origen_tipo = "departamento"
                else:
                    m = re.search(r"\d+", pedido_raw_norm)
                    pedido_id = m.group(0) if m else ""

                # Fallback de fecha (encabezado agrupado vs columna oculta 5)
                raw_fecha = current_group_date or c(5) or day_str
                m_fecha = re.search(r"(\d{2}-\d{2}-\d{4})", raw_fecha)
                fecha_grupo = m_fecha.group(1) if m_fecha else raw_fecha

                data = {
                    "fecha_grupo": fecha_grupo,  # e.g. "19-12-2025"
                    "pedido_id": pedido_id,
                    "pedido_raw": pedido_raw_norm,
                    "pedido_origen_raw": pedido_origen_raw,
                    "pedido_origen_tipo": pedido_origen_tipo,
                    "pedido_origen_id": pedido_origen_id,
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


def scrape_index_admisiones_por_identificacion(identificacion: str):
    """Scrapea el index-admisiones filtrando por número de identificación (cédula).

    Nota: usa el mismo parámetro observado en el URL del sistema:
    DocSolicitudProcedimientosAdmisionSearch[identificacion]
    """
    session = requests.Session()
    if not login(session):
        raise RuntimeError("Fallo el login")

    # Importante: en este grid, a veces la presencia de ciertos keys (aunque estén vacíos)
    # cambia el comportamiento del SearchModel. Por eso replicamos la forma del URL del navegador.
    params = {
        "DocSolicitudProcedimientosAdmisionSearch[filtro]": "",  # en tu URL aparece como filtro=
        "DocSolicitudProcedimientosAdmisionSearch[fechaBusqueda]": "",
        "DocSolicitudProcedimientosAdmisionSearch[fechaBusquedaFin]": "",
        "DocSolicitudProcedimientosAdmisionSearch[tipoAfiliacion]": "",
        "DocSolicitudProcedimientosAdmisionSearch[tipoProcedimiento]": "",
        "DocSolicitudProcedimientosAdmisionSearch[departamento]": "",
        "DocSolicitudProcedimientosAdmisionSearch[sede_id]": "",
        "DocSolicitudProcedimientosAdmisionSearch[terminada]": "",
        "DocSolicitudProcedimientosAdmisionSearch[pagado]": "",
        "DocSolicitudProcedimientosAdmisionSearch[numeroFactura]": "",
        "DocSolicitudProcedimientosAdmisionSearch[id]": "",
        "DocSolicitudProcedimientosAdmisionSearch[codigo_pedido]": "",
        "DocSolicitudProcedimientosAdmisionSearch[nroOda]": "",
        "DocSolicitudProcedimientosAdmisionSearch[usuarioAgenda]": "",
        "DocSolicitudProcedimientosAdmisionSearch[nombre_empleado]": "",
        "DocSolicitudProcedimientosAdmisionSearch[hora]": "",
        "DocSolicitudProcedimientosAdmisionSearch[paciente]": "",
        "DocSolicitudProcedimientosAdmisionSearch[identificacion]": identificacion,
        "DocSolicitudProcedimientosAdmisionSearch[estadoHistoria_id]": "",
        "DocSolicitudProcedimientosAdmisionSearch[no_historia]": "",
        "DocSolicitudProcedimientosAdmisionSearch[EMAIL]": "",
        "DocSolicitudProcedimientosAdmisionSearch[FECHA_NAC]": "",
        "DocSolicitudProcedimientosAdmisionSearch[pacienteEdad]": "",
        "DocSolicitudProcedimientosAdmisionSearch[SEXO]": "",
        "DocSolicitudProcedimientosAdmisionSearch[ciudad]": "",
        "DocSolicitudProcedimientosAdmisionSearch[afiliacionIdNombre]": "",
        "DocSolicitudProcedimientosAdmisionSearch[telefono]": "",
        "DocSolicitudProcedimientosAdmisionSearch[procedimientoId]": "",
        "DocSolicitudProcedimientosAdmisionSearch[doctor]": "",
        "DocSolicitudProcedimientosAdmisionSearch[responsableExamen]": "",
        "DocSolicitudProcedimientosAdmisionSearch[agendaDpto]": "",
        "DocSolicitudProcedimientosAdmisionSearch[optometra]": "",
        "DocSolicitudProcedimientosAdmisionSearch[fechaOptometra]": "",
        "DocSolicitudProcedimientosAdmisionSearch[CIE10]": "",
        "DocSolicitudProcedimientosAdmisionSearch[estadoAgenda]": "",
        "DocSolicitudProcedimientosAdmisionSearch[estado]": "",
        "DocSolicitudProcedimientosAdmisionSearch[observaciones]": "",
        "DocSolicitudProcedimientosAdmisionSearch[departamentoValue]": "",
        "DocSolicitudProcedimientosAdmisionSearch[ID_PROCEDENCIA]": "",
        "DocSolicitudProcedimientosAdmisionSearch[especificarPor]": "",
        "DocSolicitudProcedimientosAdmisionSearch[tiempoEspera]": "",
        "DocSolicitudProcedimientosAdmisionSearch[tiempoAtencion]": "",
        "DocSolicitudProcedimientosAdmisionSearch[codigoDerivacion]": "",
        "DocSolicitudProcedimientosAdmisionSearch[numDerivacion]": "",
        "DocSolicitudProcedimientosAdmisionSearch[maletin]": "",
        "DocSolicitudProcedimientosAdmisionSearch[corazon]": "",
        "DocSolicitudProcedimientosAdmisionSearch[asiento]": "",
        "_tog3d800b67": "all",
        "admision-sort": "id",
    }

    url = f"{BASE}/documentacion/doc-solicitud-procedimientos/index-admisiones?{urlencode(params)}"

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

    resultados = []
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

        def c(k: int) -> str:
            return cells.get(k, "")

        pedido_raw = c(6)  # Pedido (puede venir como '230395/229444' o '229445/ADMISIÓN')
        pedido_raw_norm = re.sub(r"\s+", " ", (pedido_raw or "").strip())

        # Tomamos el número ANTES de la barra como pedido_id
        pedido_id = ""
        pedido_origen_raw = ""
        pedido_origen_id = ""
        pedido_origen_tipo = ""  # 'pedido' | 'departamento' | ''

        if "/" in pedido_raw_norm:
            left, right = pedido_raw_norm.split("/", 1)
            m_left = re.search(r"\d+", left)
            pedido_id = m_left.group(0) if m_left else ""

            pedido_origen_raw = right.strip()
            m_right = re.search(r"\d+", pedido_origen_raw)
            if m_right:
                pedido_origen_id = m_right.group(0)
                pedido_origen_tipo = "pedido"
            elif pedido_origen_raw:
                pedido_origen_tipo = "departamento"
        else:
            m = re.search(r"\d+", pedido_raw_norm)
            pedido_id = m.group(0) if m else ""

        raw_fecha = current_group_date or c(5)
        m_fecha = re.search(r"(\d{2}-\d{2}-\d{4})", raw_fecha)
        fecha_grupo = m_fecha.group(1) if m_fecha else raw_fecha

        data = {
            "fecha_grupo": fecha_grupo,
            "pedido_id": pedido_id,
            "pedido_raw": pedido_raw_norm,
            "pedido_origen_raw": pedido_origen_raw,
            "pedido_origen_tipo": pedido_origen_tipo,
            "pedido_origen_id": pedido_origen_id,
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

    return {
        "url": url,
        "identificacion": identificacion,
        "total": len(resultados),
        "rows": resultados,
    }


def _safe_int(v, default=0) -> int:
    try:
        return int(str(v).strip())
    except Exception:
        return default


def _infer_lateralidad_from_row(row: dict) -> str:
    if not row:
        return "(no definido)"

    proc = re.sub(r"\s+", " ", (row.get("procedimiento") or "").strip()).upper()
    cie = re.sub(r"\s+", " ", (row.get("cie10") or "").strip()).upper()

    # Procedimiento (prioridad)
    if "AMBOS OJOS" in proc or re.search(r"\bAMBOS\b", proc):
        return "AMBOS"
    if re.search(r"\bDERECHO\b", proc):
        return "DERECHO"
    if re.search(r"\bIZQUIERDO\b", proc):
        return "IZQUIERDO"

    # CIE10 (fallback)
    if "AMBOS OJOS" in cie or "AMBOS" in cie:
        return "AMBOS"
    if "OJO DERECHO" in cie:
        return "DERECHO"
    if "OJO IZQUIERDO" in cie:
        return "IZQUIERDO"

    return "(no definido)"


def agrupar_por_codigo_derivacion(rows, include_undefined: bool = False):
    """Agrupa por codigo_derivacion y escoge el registro con menor pedido_id por cada código.

    IMPORTANTE (según tu regla):
    - La *lateralidad* que se reporta debe ser la lateralidad del **primer pedido** (el registro representativo
      con menor `pedido_id`) de ese `codigo_derivacion`, porque ese pedido es el que te interesa.
    - Aun así, dejamos un resumen opcional (`lateralidad_resumen` y `lateralidad_resumen_detalle`) por si
      te sirve auditar el grupo completo.

    Retorna una lista ordenada por `pedido_id_mas_antiguo` asc.
    """

    groups = {}

    for r in rows or []:
        code = (r.get("codigo_derivacion") or "").strip()
        if (not code) or (code == "(no definido)"):
            if not include_undefined:
                continue
            code = "(no definido)"

        pid = _safe_int(r.get("pedido_id"), default=0)
        lat_row = _infer_lateralidad_from_row(r)

        if code not in groups:
            groups[code] = {
                "codigo_derivacion": code,
                "total_registros": 0,
                "pedido_id_mas_antiguo": pid,
                "data": r,
                # Conteo global (auditoría)
                "_lat_counts": {"DERECHO": 0, "IZQUIERDO": 0, "AMBOS": 0, "(no definido)": 0},
            }

        groups[code]["total_registros"] += 1

        # Conteo global de lateralidad del grupo (auditoría)
        if lat_row not in groups[code]["_lat_counts"]:
            groups[code]["_lat_counts"][lat_row] = 0
        groups[code]["_lat_counts"][lat_row] += 1

        # Escoger representativo por menor pedido_id
        cur_pid = _safe_int(groups[code].get("pedido_id_mas_antiguo"), default=0)
        if pid and (cur_pid == 0 or pid < cur_pid):
            groups[code]["pedido_id_mas_antiguo"] = pid
            groups[code]["data"] = r

    def pick_group_lateralidad(lat_counts: dict) -> str:
        """Resumen global del grupo (NO es la lateralidad del primer pedido)."""
        d = int(lat_counts.get("DERECHO", 0) or 0)
        i = int(lat_counts.get("IZQUIERDO", 0) or 0)
        a = int(lat_counts.get("AMBOS", 0) or 0)

        if a > 0:
            return "AMBOS"
        if d > 0 and i > 0:
            return "AMBOS"
        if d > 0:
            return "DERECHO"
        if i > 0:
            return "IZQUIERDO"
        return "(no definido)"

    # Materializar resultados
    for g in groups.values():
        # 1) Lateralidad del primer pedido (la que te interesa)
        g["lateralidad"] = _infer_lateralidad_from_row(g.get("data") or {})

        # 2) Resumen global del grupo (auditoría)
        lat_counts = g.get("_lat_counts") or {}
        g["lateralidad_resumen"] = pick_group_lateralidad(lat_counts)
        g["lateralidad_resumen_detalle"] = lat_counts
        g.pop("_lat_counts", None)

    def sort_key(item):
        v = _safe_int(item.get("pedido_id_mas_antiguo"), default=0)
        return v if v > 0 else 10 ** 18

    return sorted(groups.values(), key=sort_key)


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
# Filtro por pedido_origen_raw
# =========================

def filtrar_por_pedido_origen(rows, term: str):
    """Filtra filas por coincidencia parcial (case-insensitive) en `pedido_origen_raw`.

    Ejemplos de term:
      - "ADMISIÓN"
      - "CRM"
      - "229444"
    """
    term = re.sub(r"\s+", " ", (term or "").strip())
    if not term:
        return rows

    t_upper = term.upper()
    out = []
    for r in rows or []:
        v = re.sub(r"\s+", " ", (r.get("pedido_origen_raw") or "").strip())
        if t_upper in v.upper():
            out.append(r)
    return out


# =========================
# Filtro por procedimiento
# =========================
def filtrar_por_procedimiento(rows, term: str):
    """Filtra filas por coincidencia parcial (case-insensitive) en `procedimiento`.

    Ejemplos de term:
      - "66982"
      - "OCT"
      - "BIOMETRIA"
    """
    term = re.sub(r"\s+", " ", (term or "").strip())
    if not term:
        return rows

    t_upper = term.upper()
    out = []
    for r in rows or []:
        v = re.sub(r"\s+", " ", (r.get("procedimiento") or "").strip())
        if t_upper in v.upper():
            out.append(r)
    return out


# =========================
# Ordenar por pedido_id numérico asc (vacíos/0 al final)
# =========================
def ordenar_por_pedido_id(rows):
    """Ordena filas por pedido_id (numérico) asc. Los vacíos/0 van al final."""

    def key(r):
        pid = _safe_int((r or {}).get("pedido_id"), default=0)
        return pid if pid > 0 else 10 ** 18

    return sorted(rows or [], key=key)


# =========================
# CLI
# =========================
if __name__ == "__main__":
    # Modos soportados:
    #   1) Por identificación (cédula): python scrape_index_admisiones_hc.py 0903470565 [--quiet] [--csv archivo.csv]
    #   2) Por rango de fechas:         python scrape_index_admisiones_hc.py YYYY-MM-DD YYYY-MM-DD [--quiet] [--csv archivo.csv]
    #   3) Agrupar por código derivación: añadir --group [--include-undefined] para agrupar resultados por codigo_derivacion

    if len(sys.argv) < 2:
        print(
            "Uso:\n"
            "  python scrape_index_admisiones_hc.py 0903470565 [--quiet] [--csv archivo.csv] [--origen VALOR] [--procedimiento TERM]\n"
            "  python scrape_index_admisiones_hc.py YYYY-MM-DD YYYY-MM-DD [--quiet] [--csv archivo.csv] [--origen VALOR] [--procedimiento TERM]\n"
            "\nOpciones:\n"
            "  --group               Agrupa por codigo_derivacion\n"
            "  --include-undefined   Incluye codigo_derivacion vacío/(no definido) en el agrupamiento\n"
            "  --origen VALOR        Filtra por pedido_origen_raw (ej: ADMISIÓN, CRM, 229444)\n"
            "  --procedimiento TERM  Filtra por el campo procedimiento (ej: 66982, OCT, BIOMETRIA)\n"
        )
        sys.exit(1)

    # CSV opcional
    csv_file = None
    if "--csv" in sys.argv:
        idx = sys.argv.index("--csv")
        if idx + 1 < len(sys.argv):
            csv_file = sys.argv[idx + 1]
        else:
            csv_file = "index_admisiones_export.csv"

    modo_group = "--group" in sys.argv
    include_undefined = "--include-undefined" in sys.argv

    # Filtro opcional por pedido_origen_raw (ej: "ADMISIÓN", "CRM", o un número como "229444")
    origen_filter = None
    if "--origen" in sys.argv:
        i = sys.argv.index("--origen")
        if i + 1 < len(sys.argv):
            origen_filter = sys.argv[i + 1]

    # Filtro opcional por procedimiento (ej: "66982", "OCT", "BIOMETRIA")
    procedimiento_filter = None
    if "--procedimiento" in sys.argv:
        i = sys.argv.index("--procedimiento")
        if i + 1 < len(sys.argv):
            procedimiento_filter = sys.argv[i + 1]


    # Detectar si argv[1] es fecha YYYY-MM-DD
    def es_fecha(s: str) -> bool:
        try:
            datetime.strptime(s, "%Y-%m-%d")
            return True
        except Exception:
            return False


    try:
        # Modo rango de fechas
        if len(sys.argv) >= 3 and es_fecha(sys.argv[1]) and es_fecha(sys.argv[2]):
            fecha_inicio = sys.argv[1]
            fecha_fin = sys.argv[2]
            out = scrape_index_admisiones(fecha_inicio, fecha_fin)
            if origen_filter:
                out["rows"] = filtrar_por_pedido_origen(out["rows"], origen_filter)
                out["total"] = len(out["rows"])

            if procedimiento_filter:
                out["rows"] = filtrar_por_procedimiento(out["rows"], procedimiento_filter)
                out["total"] = len(out["rows"])

            # Siempre ordenar por pedido_id asc (vacíos/0 al final)
            out["rows"] = ordenar_por_pedido_id(out["rows"])

            if csv_file:
                export_to_csv(out["rows"], csv_file)
            if modo_group:
                grouped = agrupar_por_codigo_derivacion(out["rows"], include_undefined=include_undefined)
                payload = {
                    **{k: v for k, v in out.items() if k != "rows"},
                    "total_codigos": len(grouped),
                    "grouped": grouped,
                }
                if modo_quieto:
                    print(json.dumps(payload, ensure_ascii=False))
                else:
                    print(f"✅ OK. Registros: {out['total']} | Códigos únicos: {payload['total_codigos']}")
                    print("📌 URL:", out["url"])
                    print(json.dumps(payload["grouped"], ensure_ascii=False, indent=2))
            else:
                if modo_quieto:
                    print(json.dumps(out, ensure_ascii=False))
                else:
                    print(f"✅ OK. Registros: {out['total']}")
                    print("📌 URL:", out["url"])
                    print(json.dumps(out["rows"], ensure_ascii=False, indent=2))

        # Modo identificación
        else:
            identificacion = sys.argv[1]
            out = scrape_index_admisiones_por_identificacion(identificacion)
            if origen_filter:
                out["rows"] = filtrar_por_pedido_origen(out["rows"], origen_filter)
                out["total"] = len(out["rows"])

            if procedimiento_filter:
                out["rows"] = filtrar_por_procedimiento(out["rows"], procedimiento_filter)
                out["total"] = len(out["rows"])

            # Siempre ordenar por pedido_id asc (vacíos/0 al final)
            out["rows"] = ordenar_por_pedido_id(out["rows"])

            if csv_file:
                export_to_csv(out["rows"], csv_file)
            if modo_group:
                grouped = agrupar_por_codigo_derivacion(out["rows"], include_undefined=include_undefined)
                payload = {
                    **{k: v for k, v in out.items() if k != "rows"},
                    "total_codigos": len(grouped),
                    "grouped": grouped,
                }
                if modo_quieto:
                    print(json.dumps(payload, ensure_ascii=False))
                else:
                    print(f"✅ OK. Registros: {out['total']} | Códigos únicos: {payload['total_codigos']}")
                    print("📌 URL:", out["url"])
                    print(json.dumps(payload["grouped"], ensure_ascii=False, indent=2))
            else:
                if modo_quieto:
                    print(json.dumps(out, ensure_ascii=False))
                else:
                    print(f"✅ OK. Registros: {out['total']}")
                    print("📌 URL:", out["url"])
                    print(json.dumps(out["rows"], ensure_ascii=False, indent=2))

    except Exception as e:
        print("❌ Error:", str(e))
        sys.exit(2)
