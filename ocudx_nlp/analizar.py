import sys
import json
from sentence_transformers import SentenceTransformer, util
from data_loader import obtener_palabras_clave

modelo = SentenceTransformer('paraphrase-MiniLM-L6-v2')

# Recibir texto desde stdin (útil para PHP)
entrada = json.loads(sys.stdin.read())
texto = entrada.get("examen_fisico", "").lower()

# Cargar palabras clave con sus dx_id
palabras_clave = obtener_palabras_clave()

# Vectorizar texto de entrada
emb_texto = modelo.encode(texto, convert_to_tensor=True)

# Vectorizar palabras clave
frases = [item['palabra'] for item in palabras_clave]
emb_palabras = modelo.encode(frases, convert_to_tensor=True)

# Calcular similitudes
similitudes = util.cos_sim(emb_texto, emb_palabras)[0]

# Seleccionar los mejores dx_id (con score)
sugerencias = []
for i, sim in enumerate(similitudes):
    score = float(sim)
    if score > 0.5:
        sugerencias.append({
            "dx_id": palabras_clave[i]['dx_id'],
            "palabra": palabras_clave[i]['palabra'],
            "score": round(score, 5)
        })

# Eliminar duplicados por dx_id, quedarse con el mayor score
unicos = {}
for s in sugerencias:
    dx_id = s['dx_id']
    if dx_id not in unicos or s['score'] > unicos[dx_id]['score']:
        unicos[dx_id] = s

# Imprimir JSON (para recoger desde PHP)
print(json.dumps({
    "success": True,
    "sugerencias": list(unicos.values())
}, ensure_ascii=False))