import requests
from bs4 import BeautifulSoup
import sys
import json
import re

modo_quieto = "--quiet" in sys.argv

USERNAME = "calvarado"
PASSWORD = "0923013940"
LOGIN_URL = "https://sigcenter.ddns.net:18093/site/login"

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
    buscar_url = f"https://sigcenter.ddns.net:18093/documentacion/doc-documento/paciente-list?q={hc_number}"
    r = session.get(buscar_url, headers=headers)
    match = re.search(r'"id":"(\d+)"', r.text)
    if not match:
        print("❌ No se pudo obtener el ID del paciente.")
        return
    paciente_id = match.group(1)

    # Paso 3: Buscar el enlace de modificación desde el form_id y el id del paciente
    form_id = sys.argv[1]
    log_url = f"https://sigcenter.ddns.net:18093/documentacion/doc-solicitud-procedimientos/view?id={form_id}"

    # Intento rápido: usar la vista de la solicitud (log_url) para encontrar el link update-solicitud
    # Esto evita una visita extra a ver-paciente?... cuando el link está disponible aquí.
    update_url = None
    try:
        r = session.get(log_url, headers=headers)
        soup = BeautifulSoup(r.text, "html.parser")
        link_tag = soup.find("a", href=re.compile(r"/documentacion/doc-documento/update-solicitud\?id=\d+"))
        if link_tag and link_tag.has_attr("href"):
            href = link_tag["href"]
            update_url = "https://sigcenter.ddns.net:18093" + href.replace("&amp;", "&")
            if not modo_quieto:
                print("⚡ update_url obtenido desde LOG_URL (se evitó ver-paciente extra)")
    except Exception:
        update_url = None

    # Fallback: si no se encontró en log_url, usamos el método anterior con ver-paciente?
    if not update_url:
        paciente_view_url = (
            f"https://sigcenter.ddns.net:18093/documentacion/doc-documento/ver-paciente"
            f"?DocSolicitudProcedimientosPrefacturaSearch[id]={form_id}&id={paciente_id}&view=1"
        )
        r = session.get(paciente_view_url, headers=headers)
        soup = BeautifulSoup(r.text, "html.parser")
        link_tag = soup.find("a", href=re.compile(r"/documentacion/doc-documento/update-solicitud\?id=\d+"))
        if not link_tag or not link_tag.has_attr("href"):
            print("❌ No se encontró el enlace de actualización.")
            return
        href = link_tag["href"]
        update_url = "https://sigcenter.ddns.net:18093" + href.replace("&amp;", "&")
        if not modo_quieto:
            print("ℹ️ update_url obtenido por fallback ver-paciente")

    # Paso 4: Entrar al formulario de modificación y extraer los datos
    r = session.get(update_url, headers=headers)
    soup = BeautifulSoup(r.text, "html.parser")

    # Extraer Sede (solo nombre) y Parentesco (solo nombre)
    sede_option = soup.select_one("select#docsolicitudpaciente-sede_id option[selected]")
    sede_text = sede_option.get_text(strip=True) if sede_option else ""

    parentesco_option = soup.select_one("select#docsolicitudpaciente-parentescoid option[selected]")
    parentesco_text = parentesco_option.get_text(strip=True) if parentesco_option else ""

    procedimientos = []
    filas = soup.select("tr.multiple-input-list__item")
    # Iteramos sobre cada fila de la tabla de procedimientos proyectados
    for fila in filas:
        # Buscamos el input oculto que contiene el ID del procedimiento
        input_id = fila.select_one("input[name^='DocSolicitudPaciente[proSol]'][name$='[id]']")
        # Si se encuentra el input, obtenemos su valor (el ID del procedimiento), quitando espacios
        proc_id = input_id["value"].strip() if input_id else ""

        # Buscamos el option seleccionado dentro del select que contiene el nombre del procedimiento
        option_sel = fila.select_one(
            "select[id^='docsolicitudpaciente-prosol-'][id$='-procedimiento'] option[selected]")
        # Si se encuentra el option seleccionado, obtenemos el texto (el nombre del procedimiento), quitando espacios
        proc_nombre = option_sel.text.strip() if option_sel else ""

        # Si ambos valores existen (ID y nombre), los agregamos a la lista de procedimientos
        if proc_id and proc_nombre:
            procedimientos.append({
                "form_id": form_id,
                "procedimiento_proyectado": {
                    "id": proc_id,
                    "nombre": proc_nombre
                }
            })

    # Una vez que ya tenemos todos los procedimientos proyectados desde el formulario de solicitud,
    # ahora vamos a buscar en otra página (la vista del paciente) información adicional de cada procedimiento.
    # Queremos saber cuándo se ejecutó, quién fue el doctor responsable y si ya fue dado de alta.
    tabla_view_url = f"https://sigcenter.ddns.net:18093/documentacion/doc-documento/ver-paciente?id={paciente_id}&view=1"
    r = session.get(tabla_view_url, headers=headers)
    soup_tabla = BeautifulSoup(r.text, "html.parser")

    # Construimos un mapa en una sola pasada por la tabla: {proc_id: {fecha_ejecucion, doctor, estado_alta}}
    ejecuciones_map = {}
    for row in soup_tabla.select("table.kv-grid-table tr"):
        celdas = row.find_all("td")
        # Necesitamos al menos 13 columnas para leer fecha (col 10), doctor (col 11) y estado (col 13)
        if len(celdas) >= 13:
            # Columna 5: suele contener el ID del procedimiento (a veces con sufijo tipo 176281/ADMISION)
            celda_id = celdas[4].get_text(strip=True)
            if not celda_id:
                continue

            # Extraemos el ID base (antes de cualquier "/"), para poder matchear rápido con proc_id
            proc_id_base = celda_id.split("/", 1)[0].strip()
            if not proc_id_base:
                continue

            fecha_ejecucion = celdas[9].get_text(strip=True)
            doctor = celdas[10].get_text(strip=True)
            estado_alta = "✅ Dado de Alta" if "YA FUE DADO DE ALTA" in celdas[
                12].decode_contents() else "❌ No dado de alta"

            # Si el mismo ID aparece varias veces, nos quedamos con el último encontrado
            ejecuciones_map[proc_id_base] = {
                "fecha_ejecucion": fecha_ejecucion,
                "doctor": doctor,
                "estado_alta": estado_alta
            }

    # Aplicamos el mapa a los procedimientos proyectados (lookup O(1))
    for proc in procedimientos:
        proc_id = proc["procedimiento_proyectado"]["id"]
        datos = ejecuciones_map.get(proc_id)
        if datos:
            proc["procedimiento_proyectado"]["fecha_ejecucion"] = datos.get("fecha_ejecucion", "")
            proc["procedimiento_proyectado"]["doctor"] = datos.get("doctor", "")
            proc["procedimiento_proyectado"]["estado_alta"] = datos.get("estado_alta", "")

    codigo = soup.find("input", {"id": "docsolicitudpaciente-cod_derivacion"})["value"].strip()
    input_registro = soup.find("input", {"id": "docsolicitudpaciente-fecha_registro"})
    fecha_registro = input_registro["value"].strip() if input_registro and input_registro.has_attr("value") else ""
    input_vigencia = soup.find("input", {"id": "docsolicitudpaciente-fecha_vigencia"})
    fecha_vigencia = input_vigencia["value"].strip() if input_vigencia and input_vigencia.has_attr("value") else ""

    referido_option = soup.select_one("select#docsolicitudpaciente-referido_id option[selected]")
    # print("🔍 HTML Referido Option:", referido_option)
    referido_text = ""
    if referido_option:
        referido_text = referido_option.get_text(strip=True)

    diagnostico_options = soup.select(
        "select[id^=docsolicitudpaciente-presuntivosenfermedadesexterna-][id$=-idenfermedades] option[selected]")
    diagnosticos = [opt.get_text(strip=True) for opt in diagnostico_options if opt]
    return [{
        "codigo_derivacion": codigo,
        "fecha_registro": fecha_registro,
        "fecha_vigencia": fecha_vigencia,
        "identificacion": hc_number,
        "diagnostico": "; ".join(diagnosticos),
        "referido": referido_text,
        "sede": sede_text,
        "parentesco": parentesco_text,
        "procedimientos": procedimientos
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
            if r['codigo_derivacion']:
                codigo = r['codigo_derivacion'].strip().split('SECUENCIAL')[0]
                registro = r['fecha_registro'].strip()
                vigencia = r['fecha_vigencia'].strip()
                referido = r['referido'].strip()
                diagnostico = r['diagnostico'].strip()
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
                    print(f"📌 Código Derivación: {codigo}")
                    print(f"📌 Medico: {referido}")
                    print(f"📌 Diagnostico: {diagnostico}")
                    print(f"📌 Sede: {r.get('sede', '')}")
                    print(f"📌 Parentesco: {r.get('parentesco', '')}")
                    print(f"Fecha de registro: {registro}")
                    print(f"Fecha de Vigencia: {vigencia}")
                    print("📦 Datos para API:", json.dumps(data, ensure_ascii=False, indent=2))
                    enviar_a_api(data)
                    print("📋 Procedimientos proyectados:")
                    for p in r.get("procedimientos", []):
                        datos = p["procedimiento_proyectado"]
                        print(f"{datos['id']}")
                        print(f"{datos['nombre']}")
                        print(f"{datos.get('fecha_ejecucion', 'N/D')}")
                        print(f"{datos.get('doctor', 'N/D')}")
                        print(f"{datos.get('estado_alta', 'N/D')}")
                break
