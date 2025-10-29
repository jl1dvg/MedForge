from flask_cors import CORS
from flask import Flask, request, jsonify
from sentence_transformers import SentenceTransformer, util
from data_loader import obtener_palabras_clave

app = Flask(__name__)
CORS(app)
modelo = SentenceTransformer('paraphrase-MiniLM-L6-v2')

@app.route('/analizar', methods=['POST'])
def analizar():
    data = request.get_json()
    texto = data.get("examen_fisico", "").lower()

    palabras_clave = obtener_palabras_clave()
    emb_texto = modelo.encode(texto, convert_to_tensor=True)

    frases = [item['palabra'] for item in palabras_clave]
    emb_palabras = modelo.encode(frases, convert_to_tensor=True)
    similitudes = util.cos_sim(emb_texto, emb_palabras)[0]

    sugerencias = []
    for i, sim in enumerate(similitudes):
        score = float(sim)
        if score > 0.5:
            sugerencias.append({
                "dx_id": palabras_clave[i]['dx_id'],
                "palabra": palabras_clave[i]['palabra'],
                "score": round(score, 3)
            })

    unicos = {}
    for s in sugerencias:
        dx_id = s['dx_id']
        if dx_id not in unicos or s['score'] > unicos[dx_id]['score']:
            unicos[dx_id] = s

    return jsonify({
        "success": True,
        "sugerencias": list(unicos.values())
    })

if __name__ == '__main__':
    app.run(debug=True, port=5005)