#!/usr/bin/env python3
import os
import sys
import json
import requests
from bs4 import BeautifulSoup
from datetime import datetime

BASE = "https://cive.ddns.net:8085"
LOGIN_URL = f"{BASE}/site/login"
CREATE_ENDPOINT = f"{BASE}/documentacion/agenda-doctor/create"
INDEX_URL = f"{BASE}/documentacion/agenda-doctor/index"

USER = os.getenv("SIGCENTER_USER")
PASS = os.getenv("SIGCENTER_PASS")

if not USER or not PASS:
    print(json.dumps({"ok": False, "error": "Credenciales no configuradas"}))
    sys.exit(1)

HEADERS = {
    "User-Agent": "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36"
}


def get_csrf_from_html(html: str):
    """Intenta obtener CSRF desde input hidden o meta csrf-token (Yii2)."""
    soup = BeautifulSoup(html or "", "html.parser")

    # 1) Hidden input (formularios normales)
    token = soup.find("input", {"name": "_csrf-frontend"})
    if token and token.has_attr("value"):
        return token["value"]

    # 2) Meta tag (AJAX/Yii2)
    meta = soup.find("meta", {"name": "csrf-token"}) or soup.find("meta", {"name": "_csrf-frontend"})
    if meta and meta.has_attr("content"):
        return meta["content"]

    return None


def get_csrf_from_session(session: requests.Session, html: str = ""):
    """En Yii2 normalmente el CSRF viene también en la cookie _csrf-frontend."""
    token = get_csrf_from_html(html)
    if token:
        return token

    # fallback: cookie
    cookie_token = session.cookies.get("_csrf-frontend")
    if cookie_token:
        return cookie_token

    return None


SPANISH_DAYS = [
    "Lunes", "Martes", "Miércoles", "Jueves", "Viernes", "Sábado", "Domingo"
]
SPANISH_MONTHS = {
    1: "Enero", 2: "Febrero", 3: "Marzo", 4: "Abril", 5: "Mayo", 6: "Junio",
    7: "Julio", 8: "Agosto", 9: "Septiembre", 10: "Octubre", 11: "Noviembre", 12: "Diciembre",
}


def parse_dt(s: str) -> datetime:
    # input like "2026-01-30 16:30:00"
    return datetime.strptime((s or "").strip(), "%Y-%m-%d %H:%M:%S")


def format_fecha_es(dt: datetime) -> str:
    # "Viernes, 30 Enero 2026"
    day_name = SPANISH_DAYS[dt.weekday()]
    return f"{day_name}, {dt.day:02d} {SPANISH_MONTHS[dt.month]} {dt.year}"


def format_time_parts(dt: datetime):
    # returns (hour_12_str, minute_str, meridian)
    hour_24 = dt.hour
    minute = dt.minute
    meridian = "AM" if hour_24 < 12 else "PM"
    hour_12 = hour_24 % 12
    if hour_12 == 0:
        hour_12 = 12
    return (f"{hour_12:02d}", f"{minute:02d}", meridian)


def format_time_display(dt: datetime) -> str:
    # "01:30 PM"
    h, m, mer = format_time_parts(dt)
    return f"{h}:{m} {mer}"


def login(session: requests.Session) -> bool:
    r = session.get(LOGIN_URL, headers=HEADERS, timeout=30)
    csrf = get_csrf_from_session(session, r.text)
    if not csrf:
        return False

    payload = {
        "_csrf-frontend": csrf,
        "LoginForm[username]": USER,
        "LoginForm[password]": PASS,
        "LoginForm[rememberMe]": "1",
    }

    r = session.post(LOGIN_URL, data=payload, headers=HEADERS, timeout=30)

    t = (r.text or "").lower()
    # Heurística típica Yii2
    return ("/site/logout" in t) or ("logout" in t) or ("cerrar sesi" in t)


def agendar(data):
    session = requests.Session()

    if not login(session):
        return {"ok": False, "error": "Login fallido"}

    # En Yii2 el token válido para POST suele ser el *masked* que viene en meta/input.
    # La cookie _csrf-frontend puede ser el token base y NO siempre es aceptado como parámetro.
    referer_index = (
        f"{INDEX_URL}?docSolicitud={data['docSolicitud']}&"
        f"AgendaDoctorSearch%5BTRABAJADOR_DOCTOR%5D={data['idtrabajador']}&"
        f"AgendaDoctorSearch%5BFECHA_AGENDA%5D={data['fechaInicio'][:10]}&"
        f"AgendaDoctorSearch%5BSEDE_GENERAL%5D={data.get('sede_departamento', 1)}&"
        f"AgendaDoctorSearch%5BSEDE_DEPARTAMENTO%5D={data.get('sede_departamento', 1)}"
    )

    r0 = session.get(referer_index, headers=HEADERS, timeout=30)
    csrf = get_csrf_from_session(session, r0.text)
    if not csrf:
        return {"ok": False, "error": "CSRF no encontrado (meta/input)"}

    dt_ini = parse_dt(data["fechaInicio"])
    dt_fin = parse_dt(data["fechaFin"])

    ini_hour, ini_min, ini_mer = format_time_parts(dt_ini)
    fin_hour, fin_min, fin_mer = format_time_parts(dt_fin)

    fecha_es = format_fecha_es(dt_ini)
    hora_ini_disp = format_time_display(dt_ini)
    hora_fin_disp = format_time_display(dt_fin)

    params = {
        "fechaInicio": data["fechaInicio"],
        "fechaFin": data["fechaFin"],
        "docSolicitud": str(data["docSolicitud"]),
        "idtrabajador": str(data["idtrabajador"]),
        "sede_departamento": str(data["sede_departamento"]),
        "horaIni": data["horaIni"],
        "horaFin": data["horaFin"],
        "slot": str(data.get("slot", 15)),
    }

    payload = {
        "_csrf-frontend": csrf,

        # Query-ish fields that Chrome also repeats in the multipart payload
        "fechaInicio": data["fechaInicio"],
        "fechaFin": data["fechaFin"],
        "docSolicitud": str(data["docSolicitud"]),
        "idtrabajador": str(data["idtrabajador"]),
        "sede_departamento": str(data["sede_departamento"]),
        "horaIni": str(data["horaIni"]),
        "horaFin": str(data["horaFin"]),
        "slot": str(data.get("slot", 15)),
        "_": str(data.get("_", "")),  # opcional

        # Form fields (los que realmente valida Yii)
        "AgendaDoctor[ID_TRABAJADOR]": str(data["idtrabajador"]),
        "AgendaDoctor[ID_SEDE_DEPARTAMENTO]": str(data["AgendaDoctor_ID_SEDE_DEPARTAMENTO"]),
        "AgendaDoctor[ID_OJO]": str(data["ID_OJO"]),
        "AgendaDoctor[ID_ANESTESIA]": str(data["ID_ANESTESIA"]),
        "AgendaDoctor[ID_ANESTESIOLOGO]": str(data.get("ID_ANESTESIOLOGO", "")),
        "AgendaDoctor[enviarCorreo]": str(data.get("enviarCorreo", 0)),
        "AgendaDoctor[DESCRIPCION]": str(data.get("DESCRIPCION", "")),

        # Campos que el form manda (y Yii espera) para fecha/hora
        "AgendaDoctor[FECHAINICIO]": fecha_es,
        "hour": ini_hour,
        "minute": ini_min,
        "meridian": ini_mer,
        "AgendaDoctor[HORAINICIO]": hora_ini_disp,

        # El form repite hour/minute/meridian para fin, pero con las mismas keys (se ve así en DevTools).
        # Para emularlo, enviamos una segunda tanda con sufijos distintos y luego los duplicamos al multipart.
        # (ver más abajo en build_multipart())
        "__fin_hour": fin_hour,
        "__fin_minute": fin_min,
        "__fin_meridian": fin_mer,
        "AgendaDoctor[HORAFIN]": hora_fin_disp,
    }

    headers = {
        **HEADERS,
        "X-Requested-With": "XMLHttpRequest",
        "X-CSRF-Token": csrf,
        "Referer": referer_index,
        "Origin": BASE,
    }

    # Forzar multipart/form-data como en Chrome (incluyendo claves duplicadas)
    multipart = []
    for k, v in payload.items():
        if k.startswith("__"):
            continue
        multipart.append((k, (None, str(v))))

    # Duplicados para hora fin (mismo nombre de campo)
    multipart.append(("hour", (None, str(payload["__fin_hour"]))))
    multipart.append(("minute", (None, str(payload["__fin_minute"]))))
    multipart.append(("meridian", (None, str(payload["__fin_meridian"]))))

    r = session.post(CREATE_ENDPOINT, params=params, files=multipart, headers=headers, timeout=30)

    try:
        resp = r.json()
    except Exception:
        return {
            "ok": False,
            "error": "Respuesta no JSON",
            "status": r.status_code,
            "raw": (r.text or "")[:2000],
        }

    # Algunos endpoints devuelven {success:false,...} con status 200
    if isinstance(resp, dict) and (resp.get("success") is False or resp.get("ok") is False):
        return {"ok": False, "response": resp}

    return {"ok": True, "response": resp}


if __name__ == "__main__":
    data = json.loads(sys.stdin.read())
    result = agendar(data)
    print(json.dumps(result, ensure_ascii=False))
