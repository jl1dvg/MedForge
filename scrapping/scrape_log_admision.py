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

    # print("✅ Login exitoso")

    # 3. Obtener la página de log
    log_page = session.get(LOG_URL, headers=headers)
    soup = BeautifulSoup(log_page.text, 'html.parser')

    tabla = soup.find('table', class_='detail-view')
    if not tabla:
        print("❌ No se encontró la tabla de detalle.")
        return

    filas = tabla.find_all('tr')
    campos = {}

    for fila in filas:
        th = fila.find('th').text.strip()
        td = fila.find('td').text.strip()
        campos[th] = td

    codigo_derivacion = campos.get('Codigo', '')
    identificacion = campos.get('Identificacion Afiliado', '')
    fecha_registro = campos.get('Fecha Registro', '')
    fecha_vigencia = campos.get('Fecha Vigencia', '')

    resultado = [{
        "codigo_derivacion": codigo_derivacion,
        "identificacion": identificacion,
        "fecha_registro": fecha_registro,
        "fecha_vigencia": fecha_vigencia
    }]
    return resultado


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
                print(f"📌 Código Derivación: {codigo}")
                print(f"Fecha de registro: {registro}")
                print(f"Fecha de Vigencia: {vigencia}")
                form_id = sys.argv[1] if len(sys.argv) > 1 else None
                hc_number = r.get("identificacion", "DESCONOCIDO")  # Ahora dinámico
                data = {
                    "form_id": form_id,
                    "hc_number": hc_number,
                    "codigo_derivacion": codigo
                }
                print("📦 Datos para API:", json.dumps(data, ensure_ascii=False, indent=2))
                enviar_a_api(data)
                break
            # print(f"📍 Acción: {r['ip']}")
            # print(f"🧭 Fecha y Hora: {r['navegador']}")
            # if r.get('paciente'):
            #     print(f"👤 Paciente: {r['paciente']}")
            # if r.get('identificacion'):
            #     print(f"🆔 Cédula: {r['identificacion']}")
            # if r.get('fecha_agenda'):
            #     print(f"📆 Fecha Agenda: {r['fecha_agenda']}")
            # if r.get('procedimiento'):
            #     print(f"🩺 Procedimiento: {r['procedimiento']}")
            # if r.get('formato'):
            #     print(f"🧾 Formato: {r['formato']}")
            # if r.get('cie10'):
            #     print(f"💬 CIE10: {r['cie10']}")
            # if r.get('nota_evolucion'):
            #     print(f"🧠 Nota Evolución: {r['nota_evolucion']}")
            # print(f"📄 Observaciones:\n{r['observaciones']}")
            # print("=" * 80)

# La siguiente sección que imprime solo el código de derivación ha sido comentada/eliminada para evitar duplicados y salida sin formato.
# if len(sys.argv) > 1:
#     LOG_URL = f"http://cive.ddns.net:8085/admin/log-sistema/log-admision?id={sys.argv[1]}"
#     resultados = iniciar_sesion_y_extraer_log()
#     if resultados:
#         for r in resultados:
#             if r['codigo_derivacion']:
#                 print(r['codigo_derivacion'])  # solo imprime el código
#                 break
