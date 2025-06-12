import requests
from bs4 import BeautifulSoup
import sys
import json
import re

USERNAME = "jdevera"
PASSWORD = "0925619736"
LOGIN_URL = "http://cive.ddns.net:8085/site/login"
LOG_URL = f"http://cive.ddns.net:8085/documentacion/doc-solicitud-procedimientos/view?id={sys.argv[1]}"

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
    buscar_url = f"http://cive.ddns.net:8085/documentacion/doc-documento/paciente-list?q={hc_number}"
    r = session.get(buscar_url, headers=headers)
    match = re.search(r'"id":"(\d+)"', r.text)
    if not match:
        print("❌ No se pudo obtener el ID del paciente.")
        return
    paciente_id = match.group(1)

    # Paso 3: Buscar el enlace de modificación desde el form_id y el id del paciente
    form_id = sys.argv[1]
    paciente_view_url = f"http://cive.ddns.net:8085/documentacion/doc-documento/ver-paciente?DocSolicitudProcedimientosPrefacturaSearch[id]={form_id}&id={paciente_id}&view=1"
    r = session.get(paciente_view_url, headers=headers)
    soup = BeautifulSoup(r.text, "html.parser")
    link_tag = soup.find("a", href=re.compile(r"/documentacion/doc-documento/update-solicitud\?id=\d+"))
    if not link_tag:
        print("❌ No se encontró el enlace de actualización.")
        return
    href = link_tag["href"]
    update_url = "http://cive.ddns.net:8085" + href.replace("&amp;", "&")

    # Paso 4: Entrar al formulario de modificación y extraer los datos
    r = session.get(update_url, headers=headers)
    soup = BeautifulSoup(r.text, "html.parser")

    codigo = soup.find("input", {"id": "docsolicitudpaciente-cod_derivacion"})["value"].strip()
    fecha_registro = soup.find("input", {"id": "docsolicitudpaciente-fecha_registro"})["value"].strip()
    fecha_vigencia = soup.find("input", {"id": "docsolicitudpaciente-fecha_vigencia"})["value"].strip()

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
        "referido": referido_text
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
                codigo = r['codigo_derivacion'].strip().split('SECUENCIAL')[0]  # Limpiar sufijo
                registro = r['fecha_registro'].strip()
                vigencia = r['fecha_vigencia'].strip()
                referido = r['referido'].strip()
                diagnostico = r['diagnostico'].strip()
                print(f"📌 Código Derivación: {codigo}")
                print(f"📌 Medico: {referido}")
                print(f"📌 Diagnostico: {diagnostico}")
                print(f"Fecha de registro: {registro}")
                print(f"Fecha de Vigencia: {vigencia}")
                form_id = sys.argv[1] if len(sys.argv) > 1 else None
                hc_number = r.get("identificacion", "DESCONOCIDO")  # Ahora dinámico
                data = {
                    "form_id": form_id,
                    "hc_number": hc_number,
                    "codigo_derivacion": codigo,
                    "fecha_registro": registro,
                    "fecha_vigencia": vigencia,
                    "referido": referido,
                    "diagnostico": diagnostico
                }
                print("📦 Datos para API:", json.dumps(data, ensure_ascii=False, indent=2))
                enviar_a_api(data)
                break
