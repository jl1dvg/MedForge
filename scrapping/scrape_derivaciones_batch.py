import json
import os
import sys
import re
from concurrent.futures import ThreadPoolExecutor, as_completed
from typing import List, Dict, Any, Optional
import threading

import requests
from bs4 import BeautifulSoup

USERNAME = "jdevera"
PASSWORD = "0925619736"
LOGIN_URL = "https://cive.ddns.net:8085/site/login"
LOG_ADMISION_URL = "https://cive.ddns.net:8085/documentacion/doc-solicitud-procedimientos/log-admision?id={form_id}"

headers = {"User-Agent": "Mozilla/5.0"}

modo_quieto = "--quiet" in sys.argv

thread_local = threading.local()


def obtener_csrf_token(html: str) -> Optional[str]:
    soup = BeautifulSoup(html, "html.parser")
    csrf = soup.find("input", {"name": "_csrf-frontend"})
    return csrf["value"] if csrf else None


def login(session: requests.Session) -> bool:
    r = session.get(LOGIN_URL, headers=headers)
    csrf = obtener_csrf_token(r.text)
    if not csrf:
        if not modo_quieto:
            print("❌ No se pudo obtener CSRF token.")
        return False

    payload = {
        "_csrf-frontend": csrf,
        "LoginForm[username]": USERNAME,
        "LoginForm[password]": PASSWORD,
        "LoginForm[rememberMe]": "1",
    }
    r = session.post(LOGIN_URL, data=payload, headers=headers)
    return "logout" in r.text.lower()


def normalizar_codigo(codigo: str) -> str:
    if not codigo:
        return ""
    return codigo.strip().split("SECUENCIAL")[0].strip()


def extraer_codigo(html: str) -> str:
    soup = BeautifulSoup(html, "html.parser")
    input_codigo = soup.find("input", {"id": "docsolicitudpaciente-cod_derivacion"})
    if input_codigo and input_codigo.has_attr("value"):
        return normalizar_codigo(input_codigo["value"])

    match = re.search(r"cod_derivacion\"\s*value=\"([^\"]+)\"", html)
    if match:
        return normalizar_codigo(match.group(1))

    return ""


def procesar_form(session: requests.Session, form_id: str) -> Dict[str, Any]:
    url = LOG_ADMISION_URL.format(form_id=form_id)
    try:
        response = session.get(url, headers=headers, timeout=25)
    except Exception as exc:
        return {
            "form_id": form_id,
            "cod_derivacion": "",
            "ok": False,
            "error": f"Error al consultar log-admision: {exc}",
        }

    if response.status_code != 200:
        return {
            "form_id": form_id,
            "cod_derivacion": "",
            "ok": False,
            "error": f"Respuesta {response.status_code} al consultar log-admision",
        }

    codigo = extraer_codigo(response.text)
    if not codigo:
        return {
            "form_id": form_id,
            "cod_derivacion": "",
            "ok": False,
            "error": "No se encontró código en log-admision",
        }

    return {
        "form_id": form_id,
        "cod_derivacion": codigo,
        "ok": True,
        "error": None,
    }


def session_para_hilo(cookies: Dict[str, str]) -> requests.Session:
    if not hasattr(thread_local, "session"):
        thread_local.session = requests.Session()
        thread_local.session.cookies.update(cookies)
    return thread_local.session


def worker(form_id: str, cookies: Dict[str, str]) -> Dict[str, Any]:
    session = session_para_hilo(cookies)
    return procesar_form(session, form_id)


def cargar_payload() -> List[Dict[str, Any]]:
    path = None
    for arg in sys.argv[1:]:
        if not arg.startswith("--"):
            path = arg
            break

    if path:
        with open(path, "r", encoding="utf-8") as fh:
            return json.load(fh)

    raw = sys.stdin.read().strip()
    if not raw:
        return []

    return json.loads(raw)


def main() -> int:
    payload = cargar_payload()
    if not payload:
        print("[]")
        return 0

    session = requests.Session()
    if not login(session):
        print(json.dumps([
            {
                "form_id": item.get("form_id"),
                "cod_derivacion": "",
                "ok": False,
                "error": "Fallo el login",
            }
            for item in payload
        ]))
        return 1

    cookies = session.cookies.get_dict()
    results = []
    raw_workers = os.getenv("DERIVACIONES_BATCH_WORKERS", "").strip()
    try:
        configured_workers = int(raw_workers) if raw_workers else 4
    except ValueError:
        configured_workers = 4
    max_workers = max(1, min(8, configured_workers))

    try:
        with ThreadPoolExecutor(max_workers=max_workers) as executor:
            futures = {}
            for item in payload:
                form_id = str(item.get("form_id", "")).strip()
                if not form_id:
                    results.append({
                        "form_id": form_id,
                        "cod_derivacion": "",
                        "ok": False,
                        "error": "form_id vacío",
                    })
                    continue

                futures[executor.submit(worker, form_id, cookies)] = form_id

            for future in as_completed(futures):
                try:
                    results.append(future.result())
                except MemoryError:
                    raise
                except Exception as exc:
                    form_id = futures[future]
                    results.append({
                        "form_id": form_id,
                        "cod_derivacion": "",
                        "ok": False,
                        "error": f"Error inesperado: {exc}",
                    })
    except MemoryError:
        results = []
        for item in payload:
            form_id = str(item.get("form_id", "")).strip()
            if not form_id:
                results.append({
                    "form_id": form_id,
                    "cod_derivacion": "",
                    "ok": False,
                    "error": "form_id vacío",
                })
                continue
            results.append(worker(form_id, cookies))

    print(json.dumps(results, ensure_ascii=False))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
