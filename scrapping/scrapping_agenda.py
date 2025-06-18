import requests
from bs4 import BeautifulSoup
from datetime import datetime, timedelta
import json
import sys
from datetime import date, timedelta

# Importar el clasificador de procedimientos
from clasificar_procedimiento import clasificar_procedimiento

# Credenciales
USERNAME = "jdevera"
PASSWORD = "0925619736"
LOGIN_URL = "http://cive.ddns.net:8085/site/login"
AGENDA_URL = "http://cive.ddns.net:8085/documentacion/doc-solicitud-procedimientos/index-doctor"

# Sesión persistente
session = requests.Session()
headers = {'User-Agent': 'Mozilla/5.0'}


def obtener_csrf_token(html):
    soup = BeautifulSoup(html, "html.parser")
    csrf = soup.find("input", {"name": "_csrf-frontend"})
    return csrf["value"] if csrf else None


def login():
    r = session.get(LOGIN_URL, headers=headers)
    csrf = obtener_csrf_token(r.text)
    if not csrf:
        print("❌ No se pudo obtener CSRF token.")
        sys.exit(1)

    payload = {
        "_csrf-frontend": csrf,
        "LoginForm[username]": USERNAME,
        "LoginForm[password]": PASSWORD,
        "LoginForm[rememberMe]": "1"
    }
    session.post(LOGIN_URL, data=payload, headers=headers)


def scrap_fecha(fecha):
    params = {
        "DocSolicitudProcedimientosDoctorSearch[fechaBusqueda]": fecha,
        "_tog3213ef16": "all"
    }
    r = session.get(AGENDA_URL, params=params, headers=headers)
    soup = BeautifulSoup(r.text, "html.parser")
    resultados = []

    filas = soup.select("table tbody tr")
    for fila in filas:
        celdas = [td.get_text(strip=True) for td in fila.select("td")]
        if len(celdas) >= 17:
            nombre = celdas[8]
            partes = nombre.split()
            procedimiento = celdas[13].strip()
            # Clasificar el procedimiento
            categoria = clasificar_procedimiento(procedimiento)
            resultados.append({
                "form_id": celdas[5],
                "hcNumber": celdas[9],
                "nombre_completo": nombre,
                "hora": celdas[7],
                "doctor": celdas[6],
                "afiliacion": celdas[11],
                "procedimiento_proyectado": procedimiento,
                "estado": celdas[15],
                "fecha": fecha,
                "fechaCaducidad": None if celdas[16] == '(no definido)' else celdas[16],
                "lname": partes[0] if len(partes) > 0 else '',
                "lname2": partes[1] if len(partes) > 1 else '',
                "fname": partes[2] if len(partes) > 2 else '',
                "mname": " ".join(partes[3:]) if len(partes) > 3 else '',
                "categoria": categoria,
            })
    return resultados


def main(fecha_inicio, fecha_fin):
    login()
    fecha_actual = datetime.strptime(fecha_inicio, "%Y-%m-%d")
    fin = datetime.strptime(fecha_fin, "%Y-%m-%d")
    todos = []

    while fecha_actual <= fin:
        fecha_str = fecha_actual.strftime("%Y-%m-%d")
        print(f"🔎 Consultando {fecha_str}...")
        datos = scrap_fecha(fecha_str)
        todos.extend(datos)
        fecha_actual += timedelta(days=1)

    return todos


def limpiar_nulos(data):
    return [{k: v for k, v in item.items() if v is not None} for item in data]


def enviar_a_api(datos):
    url = "https://asistentecive.consulmed.me/api/proyecciones/guardar.php"
    headers = {
        "Content-Type": "application/json",
        "Accept": "application/json"
    }
    try:
        respuesta = requests.post(url, headers=headers, json=datos)
        if respuesta.status_code == 200:
            resultado = respuesta.json()
            print("✅ Enviado a la API correctamente.")
            resultados = resultado.get("detalles", [])
            total = len(resultados)
            insertados = sum(1 for r in resultados if "guardados correctamente" in r.get("message", "").lower())
            actualizados = sum(1 for r in resultados if "actualizado o ya existente" in r.get("message", "").lower())
            fallidos = total - insertados - actualizados

            if fallidos > 0:
                print("📋 Detalles de registros fallidos:")
                for r in resultados:
                    mensaje = r.get("message", "").lower()
                    if "guardados correctamente" not in mensaje and "actualizado o ya existente" not in mensaje:
                        print(f"❌ form_id: {r.get('form_id', 'N/A')} - mensaje: {r.get('message', '')}")

            print(f"📊 Resultado final:")
            print(f"   ➕ Nuevos registros guardados: {insertados}")
            print(f"   🔄 Registros actualizados/sin cambios: {actualizados}")
            print(f"   ❌ Fallidos: {fallidos} de {total} registros totales.")
        else:
            print(f"❌ Error al enviar: {respuesta.status_code} - {respuesta.text}")
    except Exception as e:
        print(f"🛑 Excepción al enviar a API: {e}")


if __name__ == "__main__":
    from datetime import datetime, timedelta

    print(f"🕒 Ejecución: {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}")

    # Lógica predictiva
    ahora = datetime.now()
    hoy = ahora.date()

    # Por defecto, scrapear solo hoy
    fecha_inicio = hoy
    fecha_fin = hoy

    # Si es viernes (4), scrapear hasta lunes
    if hoy.weekday() == 4:
        fecha_fin = hoy + timedelta(days=3)
    # Si es domingo (6), scrapear lunes
    elif hoy.weekday() == 6:
        fecha_fin = hoy + timedelta(days=1)
    # Si son las 23h o más, scrapear hoy + 3 días
    elif ahora.hour >= 23:
        fecha_fin = hoy + timedelta(days=3)

    # Ejecutar scraping desde fecha_inicio hasta fecha_fin
    todos = main(fecha_inicio.strftime("%Y-%m-%d"), fecha_fin.strftime("%Y-%m-%d"))

    with open("resultado_agenda.json", "w", encoding="utf-8") as f:
        json.dump(todos, f, indent=2, ensure_ascii=False)
    print(f"✅ Total registros: {len(todos)}")

    if todos:
        datos_limpios = limpiar_nulos(todos)
        print(f"📦 Ejemplo de payload limpio: {json.dumps(datos_limpios[0], indent=2, ensure_ascii=False)}")
        enviar_a_api(datos_limpios)
