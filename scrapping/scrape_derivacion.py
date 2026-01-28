import requests
from bs4 import BeautifulSoup
import sys
import json
import re
import time
from urllib.parse import urlparse
import os

# Limitar hilos de OpenBLAS/Numpy para no chocar con RLIMIT_NPROC del hosting
os.environ.setdefault("OPENBLAS_NUM_THREADS", "1")
os.environ.setdefault("OMP_NUM_THREADS", "1")

modo_quieto = "--quiet" in sys.argv
ocr_habilitado = "--ocr" in sys.argv

USERNAME = "jdevera"
PASSWORD = "0925619736"
LOGIN_URL = "https://cive.ddns.net:8085/site/login"
LOG_URL = f"https://cive.ddns.net:8085/documentacion/doc-solicitud-procedimientos/view?id={sys.argv[1]}"

headers = {'User-Agent': 'Mozilla/5.0'}


def obtener_csrf_token(html):
    soup = BeautifulSoup(html, "html.parser")
    csrf = soup.find("input", {"name": "_csrf-frontend"})
    return csrf["value"] if csrf else None


def login(session):
    r = session.get(LOGIN_URL, headers=headers)
    csrf = obtener_csrf_token(r.text)
    if not csrf:
        print("❌ No se pudo obtener CSRF token.")
        return False

    payload = {
        "_csrf-frontend": csrf,
        "LoginForm[username]": USERNAME,
        "LoginForm[password]": PASSWORD,
        "LoginForm[rememberMe]": "1"
    }
    r = session.post(LOGIN_URL, data=payload, headers=headers)
    return "logout" in r.text.lower()


# === OCR helper ===
def ocr_pdf_to_text(pdf_path, lang="spa"):
    """
    Intenta extraer texto mediante OCR desde un PDF escaneado.
    Requiere tener instalados Tesseract y los módulos pdf2image y pytesseract.
    Si no están disponibles, devuelve cadena vacía y muestra un aviso.
    """
    try:
        from pdf2image import convert_from_path
        import pytesseract
    except ImportError:
        if not modo_quieto:
            print("⚠️ OCR no disponible (faltan pdf2image/pytesseract).")
        return ""

    try:
        # Convertir todas las páginas del PDF a imágenes
        pages = convert_from_path(pdf_path)
        texto_total = []
        for idx, page in enumerate(pages, start=1):
            if not modo_quieto:
                print(f"🔍 OCR página {idx} de {len(pages)} para {pdf_path}...")
            text = pytesseract.image_to_string(page, lang=lang)
            texto_total.append(text)
        return "\n".join(texto_total)
    except Exception as e:
        if not modo_quieto:
            print(f"❌ Error realizando OCR en {pdf_path}: {e}")
        return ""


def normalizar_codigo(codigo_derivacion):
    codigo_limpio = ""
    if codigo_derivacion:
        codigo_limpio = codigo_derivacion.strip().split('SECUENCIAL')[0].strip()
    codigo_limpio = re.sub(r"[^A-Za-z0-9_-]+", "_", codigo_limpio)
    return codigo_limpio or "SIN_CODIGO"


def normalizar_hc(hc_number):
    return re.sub(r"[^A-Za-z0-9_-]+", "_", hc_number) if hc_number else "SIN_HC"


def descargar_pdf_totalizado(session, paciente_id, form_id, hc_number, codigo_derivacion):
    # Guardar en storage/derivaciones/{hc}/{codigo}/
    safe_hc = normalizar_hc(str(hc_number or "SIN_HC"))
    safe_codigo = normalizar_codigo(codigo_derivacion or "SIN_CODIGO")

    output_dir = os.path.join("..", "storage", "derivaciones", safe_hc, safe_codigo)
    os.makedirs(output_dir, exist_ok=True)

    pdf_url = (
        "https://cive.ddns.net:8085/documentacion/doc-multiple-documentos/imprimir-totalizado"
        f"?id={paciente_id}&idSolicitud={form_id}&check=18"
    )

    def extraer_pdf_candidates(html):
        soup = BeautifulSoup(html, "html.parser")
        candidates = []

        iframe_pdf = soup.select_one("iframe#iframe-pdf")
        if iframe_pdf:
            src = iframe_pdf.get("src", "").strip() or iframe_pdf.get("data-src", "").strip()
            if src:
                src_path = urlparse(src).path.lower()
                if src_path.endswith(".pdf"):
                    candidates.append(src)

        for embed in soup.select("embed[original-url]"):
            original_url = embed.get("original-url", "").strip()
            if original_url and ".pdf" in original_url.lower():
                candidates.append(original_url)

        normalized_html = html.replace("\\/", "/").replace("\\u002F", "/")
        candidates.extend(
            re.findall(r"/Imprimir_temporales/[^\"'>]+?\.pdf", normalized_html, flags=re.IGNORECASE)
        )
        candidates.extend(re.findall(r"https?://[^\"'>]+?\.pdf", normalized_html, flags=re.IGNORECASE))

        seen = set()
        ordered = []
        for candidate in candidates:
            if candidate not in seen:
                seen.add(candidate)
                ordered.append(candidate)
        return ordered

    def normalizar_pdf_url(candidate_url):
        if candidate_url.startswith("/"):
            candidate_url = f"https://cive.ddns.net:8085{candidate_url}"
        return requests.utils.requote_uri(candidate_url)

    if not modo_quieto:
        print(f"⬇️ Descargando PDF totalizado: {pdf_url}")

    resp_pdf = session.get(pdf_url, headers=headers)
    if resp_pdf.status_code != 200:
        if not modo_quieto:
            print(f"⚠️ Respuesta no exitosa al descargar PDF totalizado: {resp_pdf.status_code}")
        return None

    pdf_bytes = None
    if resp_pdf.content.startswith(b"%PDF"):
        pdf_bytes = resp_pdf.content
    else:
        candidates = extraer_pdf_candidates(resp_pdf.text)
        if not candidates:
            deadline = time.monotonic() + 12
            while time.monotonic() < deadline and not candidates:
                time.sleep(1)
                poll_resp = session.get(pdf_url, headers=headers)
                if poll_resp.status_code != 200:
                    continue
                if poll_resp.content.startswith(b"%PDF"):
                    pdf_bytes = poll_resp.content
                    break
                candidates = extraer_pdf_candidates(poll_resp.text)

        if not pdf_bytes and candidates:
            for candidate in candidates:
                pdf_download_url = normalizar_pdf_url(candidate)
                if not modo_quieto:
                    print(f"⬇️ Descargando PDF detectado en HTML: {pdf_download_url}")
                pdf_download_resp = session.get(pdf_download_url, headers=headers)
                if pdf_download_resp.status_code == 200 and pdf_download_resp.content.startswith(b"%PDF"):
                    pdf_bytes = pdf_download_resp.content
                    break

    if not pdf_bytes:
        if not modo_quieto:
            print("⚠️ No se encontró URL de PDF o la respuesta no es un PDF válido.")
        return None

    # 👉 NOMBRE FINAL DEL ARCHIVO
    filename = f"derivacion_{safe_hc}_{safe_codigo}.pdf"
    filepath = os.path.join(output_dir, filename)

    if os.path.isfile(filepath):
        if not modo_quieto:
            print(f"♻️ PDF ya existe, se reutiliza: {filepath}")
        return f"storage/derivaciones/{safe_hc}/{safe_codigo}/{filename}"

    with open(filepath, "wb") as f:
        f.write(pdf_bytes)

    # esto es lo que se manda a la API (ruta relativa para guardar en DB)
    archivo_path = f"storage/derivaciones/{safe_hc}/{safe_codigo}/{filename}"

    if ocr_habilitado:
        ocr_text = ocr_pdf_to_text(filepath)
        if ocr_text and not modo_quieto:
            print("🔍 OCR (fragmento):")
            print("   " + ocr_text.replace("\n", " ")[:300] + ("..." if len(ocr_text) > 300 else ""))

    return archivo_path


def iniciar_sesion_y_extraer_log():
    session = requests.Session()

    if not login(session):
        print("❌ Fallo el login")
        return

    # Paso 1: Obtener el número de historia clínica (hc_number) desde los argumentos
    hc_number = sys.argv[2] if len(sys.argv) > 2 else None
    if not hc_number:
        print("❌ No se proporcionó hc_number como segundo argumento.")
        return

    # Paso 2: Buscar el ID interno del paciente usando el número de historia clínica
    buscar_url = f"https://cive.ddns.net:8085/documentacion/doc-documento/paciente-list?q={hc_number}"
    r = session.get(buscar_url, headers=headers)
    match = re.search(r'"id":"(\d+)"', r.text)
    if not match:
        print("❌ No se pudo obtener el ID del paciente.")
        return
    paciente_id = match.group(1)

    form_id = sys.argv[1]

    # =========================
    # A) VISTA DEL PACIENTE
    # =========================
    paciente_view_url = (
        "https://cive.ddns.net:8085/documentacion/doc-documento/ver-paciente"
        f"?DocSolicitudProcedimientosPrefacturaSearch[id]={form_id}&id={paciente_id}&view=1"
    )
    r_view = session.get(paciente_view_url, headers=headers)
    soup_view = BeautifulSoup(r_view.text, "html.parser")

    # =========================
    # B) FORMULARIO update-solicitud -> DATOS CLÍNICOS
    # =========================
    link_update = soup_view.find("a", href=re.compile(r"/documentacion/doc-documento/update-solicitud\?id=\d+"))
    if not link_update:
        print("❌ No se encontró el enlace de actualización (update-solicitud).")
        return

    update_url = "https://cive.ddns.net:8085" + link_update["href"].replace("&amp;", "&")
    r_update = session.get(update_url, headers=headers)
    soup_update = BeautifulSoup(r_update.text, "html.parser")

    # Extraer Sede (solo nombre) y Parentesco (solo nombre) desde update-solicitud
    sede_option = soup_update.select_one("select#docsolicitudpaciente-sede_id option[selected]")
    sede_text = sede_option.get_text(strip=True) if sede_option else ""

    parentesco_option = soup_update.select_one("select#docsolicitudpaciente-parentescoid option[selected]")
    parentesco_text = parentesco_option.get_text(strip=True) if parentesco_option else ""

    # Extraer procedimientos proyectados desde update-solicitud
    procedimientos = []
    filas = soup_update.select("tr.multiple-input-list__item")
    for fila in filas:
        input_id = fila.select_one("input[name^='DocSolicitudPaciente[proSol]'][name$='[id]']")
        proc_id = input_id["value"].strip() if input_id else ""

        option_sel = fila.select_one(
            "select[id^='docsolicitudpaciente-prosol-'][id$='-procedimiento'] option[selected]"
        )
        proc_nombre = option_sel.text.strip() if option_sel else ""

        if proc_id and proc_nombre:
            procedimientos.append({
                "form_id": form_id,
                "procedimiento_proyectado": {
                    "id": proc_id,
                    "nombre": proc_nombre
                }
            })

    # Completar info de ejecución desde la tabla general (igual que antes)
    tabla_view_url = f"https://cive.ddns.net:8085/documentacion/doc-documento/ver-paciente?id={paciente_id}&view=1"
    r_tabla = session.get(tabla_view_url, headers=headers)
    soup_tabla = BeautifulSoup(r_tabla.text, "html.parser")

    for proc in procedimientos:
        proc_id = proc["procedimiento_proyectado"]["id"]
        fila_encontrada = None

        for row in soup_tabla.select("table.kv-grid-table tr"):
            celdas = row.find_all("td")
            if len(celdas) >= 5:
                celda_id = celdas[4].get_text(strip=True)
                if celda_id.startswith(proc_id):
                    fila_encontrada = celdas
                    break

        if fila_encontrada and len(fila_encontrada) >= 13:
            fecha_ejecucion = fila_encontrada[9].get_text(strip=True)
            doctor = fila_encontrada[10].get_text(strip=True)
            estado_alta = "✅ Dado de Alta" if "YA FUE DADO DE ALTA" in fila_encontrada[
                12].decode_contents() else "❌ No dado de alta"

            proc["procedimiento_proyectado"]["fecha_ejecucion"] = fecha_ejecucion
            proc["procedimiento_proyectado"]["doctor"] = doctor
            proc["procedimiento_proyectado"]["estado_alta"] = estado_alta

    # Código de derivación y fechas desde update-solicitud
    codigo_input = soup_update.find("input", {"id": "docsolicitudpaciente-cod_derivacion"})
    codigo_derivacion_raw = codigo_input["value"].strip() if codigo_input and codigo_input.has_attr("value") else ""

    input_registro = soup_update.find("input", {"id": "docsolicitudpaciente-fecha_registro"})
    fecha_registro = input_registro["value"].strip() if input_registro and input_registro.has_attr("value") else ""

    input_vigencia = soup_update.find("input", {"id": "docsolicitudpaciente-fecha_vigencia"})
    fecha_vigencia = input_vigencia["value"].strip() if input_vigencia and input_vigencia.has_attr("value") else ""

    # Referido
    referido_option = soup_update.select_one("select#docsolicitudpaciente-referido_id option[selected]")
    referido_text = referido_option.get_text(strip=True) if referido_option else ""

    # Diagnósticos
    diagnostico_options = soup_update.select(
        "select[id^=docsolicitudpaciente-presuntivosenfermedadesexterna-][id$=-idenfermedades] option[selected]"
    )
    diagnosticos = [opt.get_text(strip=True) for opt in diagnostico_options] if diagnostico_options else []

    archivo_path = descargar_pdf_totalizado(
        session,
        paciente_id,
        form_id,
        hc_number,
        codigo_derivacion_raw
    )

    # Devolvemos TODO: datos clínicos + documentos
    return [{
        "codigo_derivacion": codigo_derivacion_raw,
        "fecha_registro": fecha_registro,
        "fecha_vigencia": fecha_vigencia,
        "identificacion": hc_number,
        "diagnostico": "; ".join(diagnosticos),
        "referido": referido_text,
        "sede": sede_text,
        "parentesco": parentesco_text,
        "procedimientos": procedimientos,
        "archivo_path": archivo_path
    }]


def enviar_a_api(data):
    url_api = "https://asistentecive.consulmed.me/api/prefactura/guardar_codigo_derivacion.php"
    headers = {'Content-Type': 'application/json'}
    try:
        response = requests.post(url_api, json=data, headers=headers)
        if response.status_code == 200:
            print("✅ Datos enviados correctamente a la API.")
            # Mostrar contenido de la respuesta para depuración
            print("📥 Respuesta del API:", response.text)
        else:
            print(f"❌ Error al enviar datos a la API: {response.status_code} - {response.text}")
    except Exception as e:
        print(f"❌ Excepción al enviar datos a la API: {str(e)}")


# 🔍 Ejemplo de uso
if __name__ == "__main__":
    resultados = iniciar_sesion_y_extraer_log()
    if resultados:
        for r in resultados:
            codigo_raw = r.get("codigo_derivacion", "")
            codigo = codigo_raw.strip().split('SECUENCIAL')[0] if codigo_raw else ""
            registro = r.get("fecha_registro", "").strip()
            vigencia = r.get("fecha_vigencia", "").strip()
            referido = r.get("referido", "").strip()
            diagnostico = r.get("diagnostico", "").strip()
            form_id = sys.argv[1] if len(sys.argv) > 1 else None
            hc_number = r.get("identificacion", "DESCONOCIDO")
            archivo_path = r.get("archivo_path")

            data = {
                "form_id": form_id,
                "hc_number": hc_number,
                "codigo_derivacion": codigo,
                "fecha_registro": registro,
                "fecha_vigencia": vigencia,
                "referido": referido,
                "diagnostico": diagnostico,
                "sede": r.get("sede", ""),
                "parentesco": r.get("parentesco", ""),
                "procedimientos": r.get("procedimientos", []),
                "archivo_path": archivo_path
            }

            if modo_quieto:
                print(json.dumps(data))
            else:
                print(f"📌 Código Derivación: {codigo or '(vacío)'}")
                print(f"📌 Medico: {referido or '(vacío)'}")
                print(f"📌 Diagnostico: {diagnostico or '(vacío)'}")
                print(f"📌 Sede: {r.get('sede', '') or '(vacío)'}")
                print(f"📌 Parentesco: {r.get('parentesco', '') or '(vacío)'}")
                print(f"Fecha de registro: {registro or '(vacía)'}")
                print(f"Fecha de Vigencia: {vigencia or '(vacía)'}")
                print("📦 Datos para API:", json.dumps(data, ensure_ascii=False, indent=2))
                if archivo_path:
                    print(f"📄 PDF totalizado guardado en: {archivo_path}")
                else:
                    print("⚠️ No se guardó PDF totalizado.")
                if codigo:
                    enviar_a_api(data)
                else:
                    print("⚠️ Código de derivación vacío, no se envía a la API.")
                print("📋 Procedimientos proyectados:")
                for p in r.get("procedimientos", []):
                    datos = p["procedimiento_proyectado"]
                    print(f"{datos['id']}")
                    print(f"{datos['nombre']}")
                    print(f"{datos.get('fecha_ejecucion', 'N/D')}")
                    print(f"{datos.get('doctor', 'N/D')}")
                    print(f"{datos.get('estado_alta', 'N/D')}")
            break
