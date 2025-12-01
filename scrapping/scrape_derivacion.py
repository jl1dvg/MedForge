import requests
from bs4 import BeautifulSoup
import sys
import json
import re
import os
import base64

# OCR dependencies (optional)
try:
    from pdf2image import convert_from_path
    import pytesseract
except ImportError:
    convert_from_path = None
    pytesseract = None

modo_quieto = "--quiet" in sys.argv

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
    if convert_from_path is None or pytesseract is None:
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
            estado_alta = "✅ Dado de Alta" if "YA FUE DADO DE ALTA" in fila_encontrada[12].decode_contents() else "❌ No dado de alta"

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

    # =========================
    # C) MODAL upload-doc-afiliacion -> DOCUMENTOS
    # =========================
    pattern = rf"/documentacion/doc-documento/upload-doc-afiliacion\?docSolPro={form_id}"
    link_upload = soup_view.find("a", href=re.compile(pattern))
    if not link_upload:
        print("❌ No se encontró el enlace de upload-doc-afiliacion para este form_id.")
        return

    href = link_upload["href"]
    target_url = "https://cive.ddns.net:8085" + href.replace("&amp;", "&")
    print(f"🔗 URL upload-doc-afiliacion: {target_url}")

    r_upload = session.get(target_url, headers=headers)
    if not modo_quieto:
        html_preview = r_upload.text[:2000]
        print("===== PREVIEW HTML MODAL upload-doc-afiliacion =====")
        print(html_preview)
        print("===== FIN PREVIEW HTML MODAL =====")
    soup_upload = BeautifulSoup(r_upload.text, "html.parser")

    # Documentos por afiliación desde el modal
    documentos_afiliacion = []
    for li in soup_upload.select("div.doc-documento-upfile ul.nav li"):
        a_tag = li.find("a")
        if not a_tag:
            continue
        href_file = a_tag.get("href", "").strip()
        title_file = a_tag.get("title", "").strip()
        button = a_tag.find("button")
        label_btn = button.get_text(strip=True) if button else ""

        try:
            from urllib.parse import urlparse, parse_qs
            parsed = urlparse(href_file)
            qs = parse_qs(parsed.query)
            doc_id = qs.get("id", [None])[0]
            id_pro = qs.get("idPro", [None])[0]
        except Exception:
            doc_id = None
            id_pro = None

        documentos_afiliacion.append({
            "id": doc_id,
            "idPro": id_pro,
            "title": title_file,
            "label": label_btn,
            "href": href_file
        })

    # Para cada documento de afiliación, seguir el enlace file-afiliacion y extraer detalles del archivo
    for doc in documentos_afiliacion:
        href_rel = doc.get("href", "")
        if not href_rel:
            continue
        try:
            file_url = "https://cive.ddns.net:8085" + href_rel.replace("&amp;", "&")

            ajax_headers = headers.copy()
            ajax_headers["X-Requested-With"] = "XMLHttpRequest"
            ajax_headers["Referer"] = target_url

            r_file = session.get(file_url, headers=ajax_headers)

            if not modo_quieto:
                print("===== DEBUG file-afiliacion =====")
                print("URL:", file_url)
                print("status_code:", r_file.status_code)
                print(r_file.text[:500])
                print("===== FIN DEBUG file-afiliacion =====")

            html_modal = r_file.text
            try:
                data_json = r_file.json()
                if isinstance(data_json, dict) and "content" in data_json:
                    html_modal = data_json.get("content", "")
            except ValueError:
                pass

            soup_file = BeautifulSoup(html_modal, "html.parser")

            h4 = soup_file.select_one("div.doc-documento-proc-form h4.box-title")
            caption_modal = h4.get_text(strip=True) if h4 else ""

            obj = soup_file.select_one("object.kv-preview-data")
            file_data_path = obj.get("data", "").strip() if obj and obj.has_attr("data") else ""

            if not file_data_path:
                html_modal_text = soup_file.decode()
                m = re.search(r'"initialPreview"\s*:\s*\[\s*"([^"]+)"', html_modal_text)
                if m:
                    file_data_path = m.group(1).replace("\\/", "/").strip()

            if not modo_quieto:
                print("DEBUG object.kv-preview-data ->", "ENCONTRADO" if obj else "NO ENCONTRADO")
                print("DEBUG file_data_path ->", repr(file_data_path))

            doc["caption_modal"] = caption_modal
            doc["file_data_path"] = file_data_path

            if file_data_path:
                pdf_url = "https://cive.ddns.net:8085" + file_data_path
                doc["file_url"] = pdf_url

                try:
                    resp_pdf = session.get(pdf_url, headers=headers)
                    if resp_pdf.status_code == 200:
                        output_dir = "afiliacion_pdfs"
                        os.makedirs(output_dir, exist_ok=True)
                        doc_id = doc.get("id") or "unknown"

                        # Nombre: HC + código derivación limpio + id doc
                        codigo_file = codigo_derivacion_raw.strip().split('SECUENCIAL')[0] if codigo_derivacion_raw else ""
                        codigo_file = re.sub(r"[^A-Za-z0-9_-]+", "_", codigo_file) or "SIN_COD"
                        hc_safe = re.sub(r"[^A-Za-z0-9_-]+", "_", hc_number) if hc_number else "SIN_HC"

                        filename = f"{hc_safe}_{codigo_file}_{doc_id}.pdf"
                        filepath = os.path.join(output_dir, filename)
                        with open(filepath, "wb") as f:
                            f.write(resp_pdf.content)
                        doc["saved_path"] = filepath

                        doc["file_base64"] = base64.b64encode(resp_pdf.content).decode("utf-8")

                        # Intentar OCR sobre el PDF guardado
                        ocr_text = ocr_pdf_to_text(filepath)
                        if ocr_text:
                            # Guardamos el texto completo por si se quiere procesar más adelante
                            doc["ocr_text"] = ocr_text
                            # Además, un pequeño fragmento para depuración rápida
                            doc["ocr_excerpt"] = ocr_text[:500]
                except Exception:
                    pass

        except Exception:
            continue

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
        "documentos_afiliacion": documentos_afiliacion
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
                "procedimientos": r.get("procedimientos", [])
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
                # Mostrar documentos por afiliación obtenidos del modal, si existen
                docs = r.get("documentos_afiliacion", [])
                if docs:
                    print("📎 Documentos por afiliación (modal upload-doc-afiliacion):")
                    for d in docs:
                        print(f"- [{d.get('id')}] {d.get('label')} ({d.get('title')}) -> {d.get('href')}")
                        # Si ya se pudo abrir el modal de file-afiliacion, mostrar detalles del archivo
                        if d.get("file_data_path") or d.get("caption_modal"):
                            print(f"    · Caption modal: {d.get('caption_modal') or '(sin caption)'}")
                            print(f"    · Ruta interna: {d.get('file_data_path') or '(sin ruta)'}")
                        if d.get("file_url"):
                            print(f"    · URL archivo: {d.get('file_url')}")
                        if d.get("saved_path"):
                            print(f"    · Guardado en: {d.get('saved_path')}")
                        if d.get("ocr_excerpt"):
                            print("    · OCR (fragmento):")
                            print("      " + d.get("ocr_excerpt").replace("\n", " ")[:300] + ("..." if len(d.get("ocr_excerpt")) > 300 else ""))
                else:
                    print("📎 Documentos por afiliación: (ninguno encontrado en el modal)")
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
