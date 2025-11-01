import mysql.connector

def obtener_palabras_clave():
    conn = mysql.connector.connect(
        host="127.0.0.1",
        user="root",
        password="",
        database="ocudx_local"
    )
    cursor = conn.cursor(dictionary=True)
    cursor.execute("SELECT dx_id, palabra FROM palabras_clave")
    data = cursor.fetchall()
    cursor.close()
    conn.close()
    return data
