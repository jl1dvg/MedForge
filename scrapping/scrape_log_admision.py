import requests
from bs4 import BeautifulSoup
import sys

USERNAME = "jdevera"
PASSWORD = "0925619736"
LOGIN_URL = "http://cive.ddns.net:8085/site/login"
LOG_URL = "http://cive.ddns.net:8085/admin/log-sistema/log-admision?id=151132"

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

    #print("✅ Login exitoso")

    # 3. Obtener la página de log
    log_page = session.get(LOG_URL, headers=headers)
    soup = BeautifulSoup(log_page.text, 'html.parser')
    tabla = soup.find('table')

    if not tabla:
        print("❌ No se encontró la tabla.")
        return

    # def extraer_campos_clave(obs):
    #     campos = {
    #         'paciente': '',
    #         'identificacion': '',
    #         'fecha_agenda': '',
    #         'procedimiento': '',
    #         'formato': '',
    #         'cie10': '',
    #         'nota_evolucion': ''
    #     }
    #
    #     # Forzar saltos de línea donde los campos están pegados
    #     raw = obs.replace('ID PEDIDO', '\nID PEDIDO') \
    #         .replace('Paciente Nombre', '\nPaciente Nombre') \
    #         .replace('Identificación', '\nIdentificación') \
    #         .replace('Fecha Agenda', '\nFecha Agenda') \
    #         .replace('Nombre Procedimiento', '\nNombre Procedimiento') \
    #         .replace('Formato Procedimiento', '\nFormato Procedimiento') \
    #         .replace('CIE10', '\nCIE10') \
    #         .replace('Nota Evolución', '\nNota Evolución')
    #
    #     for linea in raw.splitlines():
    #         if 'Paciente Nombre' in linea:
    #             campos['paciente'] = linea.split(':', 1)[-1].strip()
    #         elif 'Identificación' in linea:
    #             campos['identificacion'] = linea.split(':', 1)[-1].strip()
    #         elif 'Fecha Agenda' in linea:
    #             campos['fecha_agenda'] = linea.split(':', 1)[-1].strip()
    #         elif 'Nombre Procedimiento' in linea:
    #             campos['procedimiento'] = linea.split(':', 1)[-1].strip()
    #         elif 'Formato Procedimiento' in linea:
    #             campos['formato'] = linea.split(':', 1)[-1].strip()
    #         elif 'CIE10' in linea:
    #             campos['cie10'] = linea.split(':', 1)[-1].strip()
    #         elif 'Nota Evolución' in linea:
    #             campos['nota_evolucion'] = linea.split(':', 1)[-1].strip()
    #     return campos

    filas = tabla.find_all('tr')[1:]
    datos = []

    for fila in filas:
        columnas = fila.find_all('td')
        if len(columnas) < 6:
            continue

        observaciones = columnas[6].text.strip()
        # campos_extra = extraer_campos_clave(observaciones)

        # Extraer código derivación si existe
        codigo_derivacion = ""
        if "CODIGO DERIVACÓN:" in observaciones.upper():
            for linea in observaciones.splitlines():
                if "CODIGO DERIVACÓN" in linea.upper():
                    partes = linea.upper().split("CODIGO DERIVACÓN:")
                    if len(partes) > 1:
                        codigo_derivacion = partes[1].strip().split()[0]
                    break

        registro = {
            'fecha_hora': columnas[0].text.strip(),
            'usuario': columnas[1].text.strip(),
            'ip': columnas[2].text.strip(),
            'navegador': columnas[3].text.strip(),
            'accion': columnas[4].text.strip(),
            'observaciones': observaciones,
            'codigo_derivacion': codigo_derivacion or None,
            # **campos_extra
        }
        datos.append(registro)

    return datos


# 🔍 Ejemplo de uso
if __name__ == "__main__":
    resultados = iniciar_sesion_y_extraer_log()
    if resultados:
        for r in resultados:
            if r['codigo_derivacion']:
                # paciente = r.get('paciente', '').strip()
                codigo = r['codigo_derivacion'].strip().split('SECUENCIAL')[0]  # Limpiar sufijo
                # print("✅ Login exitoso")
                # print(f"👤 Paciente: {paciente or 'Desconocido'}")
                # print(f"🆔 Form ID: {sys.argv[1]}")
                print(f"📌 Código Derivación: {codigo}")
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

import json


def enviar_a_api(data):
    url_api = "https://cive.consulmed.me/api/guardar_codigo_derivacion.php"
    headers = {'Content-Type': 'application/json'}
    try:
        response = requests.post(url_api, json=data, headers=headers)
        if response.status_code == 200:
            print("✅ Datos enviados correctamente a la API.")
        else:
            print(f"❌ Error al enviar datos a la API: {response.status_code} - {response.text}")
    except Exception as e:
        print(f"❌ Excepción al enviar datos a la API: {str(e)}")


# La siguiente sección que imprime solo el código de derivación ha sido comentada/eliminada para evitar duplicados y salida sin formato.
# if len(sys.argv) > 1:
#     LOG_URL = f"http://cive.ddns.net:8085/admin/log-sistema/log-admision?id={sys.argv[1]}"
#     resultados = iniciar_sesion_y_extraer_log()
#     if resultados:
#         for r in resultados:
#             if r['codigo_derivacion']:
#                 print(r['codigo_derivacion'])  # solo imprime el código
#                 break
