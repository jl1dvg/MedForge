import json
import os
import re
import sys
import threading
from concurrent.futures import ThreadPoolExecutor, as_completed
from typing import Any, Dict, List, Optional

import requests
from bs4 import BeautifulSoup

USERNAME = "calvarado"
PASSWORD = "0923013940"
LOGIN_URL = "https://sigcenter.ddns.net:18093/site/login"
BASE_URL = "https://sigcenter.ddns.net:18093"

headers = {"User-Agent": "Mozilla/5.0"}
modo_quieto = "--quiet" in sys.argv
thread_local = threading.local()


def obtener_csrf_token(html: str) -> Optional[str]:
    soup = BeautifulSoup(html, "html.parser")
    csrf = soup.find("input", {"name": "_csrf-frontend"})
    return csrf["value"] if csrf else None


def login(session: requests.Session) -> bool:
    response = session.get(LOGIN_URL, headers=headers, timeout=25)
    csrf = obtener_csrf_token(response.text)
    if not csrf:
        return False

    payload = {
        "_csrf-frontend": csrf,
        "LoginForm[username]": USERNAME,
        "LoginForm[password]": PASSWORD,
        "LoginForm[rememberMe]": "1",
    }
    response = session.post(LOGIN_URL, data=payload, headers=headers, timeout=25)
    return "logout" in response.text.lower()


def normalizar_codigo(codigo: str) -> str:
    if not codigo:
        return ""
    return codigo.strip().split("SECUENCIAL")[0].strip()


def session_para_hilo(cookies: Dict[str, str]) -> requests.Session:
    if not hasattr(thread_local, "session"):
        thread_local.session = requests.Session()
        thread_local.session.cookies.update(cookies)
    return thread_local.session


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


def get_paciente_id(session: requests.Session, hc_number: str) -> Optional[str]:
    buscar_url = f"{BASE_URL}/documentacion/doc-documento/paciente-list?q={hc_number}"
    response = session.get(buscar_url, headers=headers, timeout=25)
    match = re.search(r'"id":"(\d+)"', response.text)
    return match.group(1) if match else None


def get_update_url(session: requests.Session, form_id: str, paciente_id: str) -> Optional[str]:
    log_url = f"{BASE_URL}/documentacion/doc-solicitud-procedimientos/view?id={form_id}"

    try:
        response = session.get(log_url, headers=headers, timeout=25)
        soup = BeautifulSoup(response.text, "html.parser")
        link_tag = soup.find("a", href=re.compile(r"/documentacion/doc-documento/update-solicitud\?id=\d+"))
        if link_tag and link_tag.has_attr("href"):
            return BASE_URL + link_tag["href"].replace("&amp;", "&")
    except Exception:
        pass

    paciente_view_url = (
        f"{BASE_URL}/documentacion/doc-documento/ver-paciente"
        f"?DocSolicitudProcedimientosPrefacturaSearch[id]={form_id}&id={paciente_id}&view=1"
    )
    response = session.get(paciente_view_url, headers=headers, timeout=25)
    soup = BeautifulSoup(response.text, "html.parser")
    link_tag = soup.find("a", href=re.compile(r"/documentacion/doc-documento/update-solicitud\?id=\d+"))
    if link_tag and link_tag.has_attr("href"):
        return BASE_URL + link_tag["href"].replace("&amp;", "&")

    return None


def extraer_texto_selected(soup: BeautifulSoup, selector: str) -> str:
    option = soup.select_one(selector)
    return option.get_text(strip=True) if option else ""


def extraer_diagnostico(soup: BeautifulSoup) -> str:
    options = soup.select(
        "select[id^=docsolicitudpaciente-presuntivosenfermedadesexterna-][id$=-idenfermedades] option[selected]"
    )
    diagnosticos = [opt.get_text(strip=True) for opt in options if opt]
    return "; ".join(diagnosticos)


def procesar_form(session: requests.Session, form_id: str, hc_number: str) -> Dict[str, Any]:
    if not hc_number:
        return {
            "form_id": form_id,
            "hc_number": hc_number,
            "cod_derivacion": "",
            "ok": False,
            "error": "hc_number vacío",
        }

    paciente_id = get_paciente_id(session, hc_number)
    if not paciente_id:
        return {
            "form_id": form_id,
            "hc_number": hc_number,
            "cod_derivacion": "",
            "ok": False,
            "error": "No se pudo obtener el ID del paciente",
        }

    update_url = get_update_url(session, form_id, paciente_id)
    if not update_url:
        return {
            "form_id": form_id,
            "hc_number": hc_number,
            "cod_derivacion": "",
            "ok": False,
            "error": "No se encontró el enlace de actualización",
        }

    response = session.get(update_url, headers=headers, timeout=25)
    soup = BeautifulSoup(response.text, "html.parser")

    codigo_input = soup.find("input", {"id": "docsolicitudpaciente-cod_derivacion"})
    codigo = normalizar_codigo(codigo_input["value"]) if codigo_input and codigo_input.has_attr("value") else ""
    if not codigo:
        return {
            "form_id": form_id,
            "hc_number": hc_number,
            "cod_derivacion": "",
            "ok": False,
            "error": "No se encontró código de derivación",
        }

    input_registro = soup.find("input", {"id": "docsolicitudpaciente-fecha_registro"})
    input_vigencia = soup.find("input", {"id": "docsolicitudpaciente-fecha_vigencia"})
    fecha_registro = input_registro["value"].strip() if input_registro and input_registro.has_attr("value") else ""
    fecha_vigencia = input_vigencia["value"].strip() if input_vigencia and input_vigencia.has_attr("value") else ""

    referido = extraer_texto_selected(soup, "select#docsolicitudpaciente-referido_id option[selected]")
    sede = extraer_texto_selected(soup, "select#docsolicitudpaciente-sede_id option[selected]")
    parentesco = extraer_texto_selected(soup, "select#docsolicitudpaciente-parentescoid option[selected]")
    diagnostico = extraer_diagnostico(soup)

    return {
        "form_id": form_id,
        "hc_number": hc_number,
        "cod_derivacion": codigo,
        "codigo_derivacion": codigo,
        "fecha_registro": fecha_registro,
        "fecha_vigencia": fecha_vigencia,
        "referido": referido,
        "diagnostico": diagnostico,
        "sede": sede,
        "parentesco": parentesco,
        "ok": True,
        "error": None,
    }


def worker(form_id: str, hc_number: str, cookies: Dict[str, str]) -> Dict[str, Any]:
    session = session_para_hilo(cookies)
    return procesar_form(session, form_id, hc_number)


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
                "hc_number": item.get("hc_number"),
                "cod_derivacion": "",
                "ok": False,
                "error": "Fallo el login",
            }
            for item in payload
        ], ensure_ascii=False))
        return 1

    cookies = session.cookies.get_dict()
    raw_workers = os.getenv("DERIVACIONES_BATCH_WORKERS", "").strip()
    try:
        configured_workers = int(raw_workers) if raw_workers else 4
    except ValueError:
        configured_workers = 4
    max_workers = max(1, min(8, configured_workers))

    results: List[Dict[str, Any]] = []
    try:
        with ThreadPoolExecutor(max_workers=max_workers) as executor:
            futures = {}
            for item in payload:
                form_id = str(item.get("form_id", "")).strip()
                hc_number = str(item.get("hc_number", "")).strip()
                if not form_id:
                    results.append({
                        "form_id": form_id,
                        "hc_number": hc_number,
                        "cod_derivacion": "",
                        "ok": False,
                        "error": "form_id vacío",
                    })
                    continue

                futures[executor.submit(worker, form_id, hc_number, cookies)] = (form_id, hc_number)

            for future in as_completed(futures):
                try:
                    results.append(future.result())
                except MemoryError:
                    raise
                except Exception as exc:
                    form_id, hc_number = futures[future]
                    results.append({
                        "form_id": form_id,
                        "hc_number": hc_number,
                        "cod_derivacion": "",
                        "ok": False,
                        "error": f"Error inesperado: {exc}",
                    })
    except MemoryError:
        results = []
        for item in payload:
            form_id = str(item.get("form_id", "")).strip()
            hc_number = str(item.get("hc_number", "")).strip()
            if not form_id:
                results.append({
                    "form_id": form_id,
                    "hc_number": hc_number,
                    "cod_derivacion": "",
                    "ok": False,
                    "error": "form_id vacío",
                })
                continue
            results.append(worker(form_id, hc_number, cookies))

    print(json.dumps(results, ensure_ascii=False))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
