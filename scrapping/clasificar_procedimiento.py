# clasificar_procedimiento.py

import re

# Diccionario de categorías y palabras clave asociadas
CATEGORIAS = {
    "CIRUGÍA DE CATARATA": ["CATARATA", "FACOEMULSIFICACION", "LENTE", "INTRAOCULAR", "EXTRACAPSULAR", "IRRIGACION", "ASPIRACION", "MANUAL"],
    "VITRECTOMÍA": ["VITRECTOMIA", "VITREO", "PARS", "PLANA", "SUBRETINAL", "CAMARA", "POSTERIOR", "ANTERIOR"],
    "CONSULTA / SERVICIO GENERAL": ["CONSULTA", "SERVICIOS", "GENERALES", "OFTALMOLOGICOS"],
    "IMÁGENES": ["IMAGENES", "BIOMETRIA", "CAMPO", "PAQUIMETRIA"],
    "LÁSER": ["LASER", "LÁSER", "FOTOCOAGULACION", "MULTISPOT"],
    "INYECCIONES": ["INYECCION", "SUBCONJUNTIVAL", "INTRAVITREA", "MEDICAMENTO", "AVASTIN"],
    "GLAUCOMA / DRENAJE": ["GLAUCOMA", "DRENAJE", "TRABECULECTOMIA", "VALVULA", "IRIDECTOMIA"],
    "ESTRABISMO / PTOSIS": ["ESTRABISMO", "PTOSIS", "SUTURA", "MUSCULO", "TRANSPLANTE"],
    "PÁRPADOS / CONJUNTIVA": ["PARPADO", "CONJUNTIVA", "PALPEBRAL", "BLEFAROPLASTIA", "CONJUNTIVOPLASTIA"],
    "PTERIGIÓN": ["PTERIGION", "INJERTO", "LIMBICA", "EXCISION", "RESECCION"],
    "TUMORES / EXTIRPACIONES": ["TUMOR", "EXTIRPACION", "RESECCION"],
}

def clasificar_procedimiento(texto):
    texto = texto.upper()
    for categoria, palabras in CATEGORIAS.items():
        for palabra in palabras:
            if re.search(rf"\b{palabra}\b", texto):
                return categoria
    return "OTROS"

# Si se ejecuta como script directo
if __name__ == "__main__":
    import sys
    if len(sys.argv) > 1:
        texto = " ".join(sys.argv[1:])
        print(f"📋 Procedimiento: {texto}")
        print(f"📂 Categoría sugerida: {clasificar_procedimiento(texto)}")
    else:
        print("Uso: python3 clasificar_procedimiento.py 'Texto del procedimiento'")