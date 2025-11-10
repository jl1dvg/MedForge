#!/usr/bin/env python3
import base64
import io
import json
import math
import sys
from typing import Any, Dict, List, Optional

try:
    from PIL import Image, ImageOps, ImageFilter
except Exception:  # pragma: no cover - optional dependency fallback
    Image = None  # type: ignore


def respond(ok: bool, **payload: Any) -> None:
    response = {"ok": ok}
    response.update(payload)
    sys.stdout.write(json.dumps(response, ensure_ascii=False))


def load_request() -> Dict[str, Any]:
    raw = sys.stdin.read()
    if not raw:
        return {}
    try:
        return json.loads(raw)
    except json.JSONDecodeError:
        return {}


def ensure_image(binary: bytes) -> Optional["Image.Image"]:
    if Image is None:
        return None
    try:
        img = Image.open(io.BytesIO(binary))
        img = ImageOps.exif_transpose(img)
        return img.convert("L")
    except Exception:
        return None


def normalize_vector(values: List[float]) -> Optional[List[float]]:
    if not values:
        return None

    norm = math.sqrt(sum(v * v for v in values))
    if norm <= 1e-9:
        return None

    return [v / norm for v in values]


def cosine_similarity(a: List[float], b: List[float]) -> Optional[float]:
    length = min(len(a), len(b))
    if length == 0:
        return None
    dot = 0.0
    norm_a = 0.0
    norm_b = 0.0
    for i in range(length):
        va = float(a[i])
        vb = float(b[i])
        dot += va * vb
        norm_a += va * va
        norm_b += vb * vb
    if norm_a <= 0 or norm_b <= 0:
        return None
    similarity = dot / (math.sqrt(norm_a) * math.sqrt(norm_b))
    similarity = max(-1.0, min(1.0, similarity))
    return (similarity + 1.0) / 2.0


def l1_similarity(a: List[float], b: List[float]) -> Optional[float]:
    length = min(len(a), len(b))
    if length == 0:
        return None
    diff = sum(abs(float(a[i]) - float(b[i])) for i in range(length))
    max_diff = float(length)
    score = 1.0 - min(1.0, diff / max_diff)
    return score


def face_template(binary: bytes) -> Dict[str, Any]:
    if Image is None:
        digest = base64.b16encode(__import__("hashlib").sha256(binary).digest()).decode("ascii")
        return {
            "provider": "python-cli",
            "algorithm": "hash-only",
            "hash": digest,
        }

    image = ensure_image(binary)
    if image is None:
        digest = base64.b16encode(__import__("hashlib").sha256(binary).digest()).decode("ascii")
        return {
            "provider": "python-cli",
            "algorithm": "hash-only",
            "hash": digest,
        }

    resized = ImageOps.fit(image, (96, 96), method=Image.Resampling.LANCZOS)
    enhanced = ImageOps.autocontrast(resized.filter(ImageFilter.UnsharpMask(radius=1, percent=150)))
    downsampled = enhanced.resize((48, 48), Image.Resampling.LANCZOS)
    pixels = [px / 255.0 for px in downsampled.getdata()]
    normalized = normalize_vector(pixels)
    if normalized is None:
        digest = base64.b16encode(__import__("hashlib").sha256(binary).digest()).decode("ascii")
        return {
            "provider": "python-cli",
            "algorithm": "hash-only",
            "hash": digest,
        }

    return {
        "provider": "python-cli",
        "algorithm": "pil-face-l2-48",
        "vector": [round(v, 6) for v in normalized],
        "size": 48,
    }


def face_compare(reference: Dict[str, Any], sample: Dict[str, Any]) -> Optional[float]:
    if reference.get("algorithm") == "hash-only" and sample.get("algorithm") == "hash-only":
        return 1.0 if reference.get("hash") == sample.get("hash") else 0.0

    ref_vec = reference.get("vector")
    sample_vec = sample.get("vector")
    if not isinstance(ref_vec, list) or not isinstance(sample_vec, list):
        return None

    return cosine_similarity(ref_vec, sample_vec)


def signature_template(binary: bytes) -> Dict[str, Any]:
    if Image is None:
        digest = base64.b16encode(__import__("hashlib").sha256(binary).digest()).decode("ascii")
        return {
            "provider": "python-cli",
            "algorithm": "hash-only",
            "hash": digest,
        }

    image = ensure_image(binary)
    if image is None:
        digest = base64.b16encode(__import__("hashlib").sha256(binary).digest()).decode("ascii")
        return {
            "provider": "python-cli",
            "algorithm": "hash-only",
            "hash": digest,
        }

    inverted = ImageOps.invert(image)
    contrasted = ImageOps.autocontrast(inverted)
    downsampled = contrasted.resize((96, 48), Image.Resampling.LANCZOS)
    pixels = [px / 255.0 for px in downsampled.getdata()]
    normalized = [round(p, 6) for p in pixels]

    return {
        "provider": "python-cli",
        "algorithm": "pil-signature-grid-96x48",
        "vector": normalized,
        "width": 96,
        "height": 48,
    }


def signature_compare(reference: Dict[str, Any], sample: Dict[str, Any]) -> Optional[float]:
    if reference.get("algorithm") == "hash-only" and sample.get("algorithm") == "hash-only":
        return 1.0 if reference.get("hash") == sample.get("hash") else 0.0

    ref_vec = reference.get("vector")
    sample_vec = sample.get("vector")
    if not isinstance(ref_vec, list) or not isinstance(sample_vec, list):
        return None

    return l1_similarity(ref_vec, sample_vec)


def handle_template(modality: str, binary_b64: str) -> None:
    try:
        binary = base64.b64decode(binary_b64)
    except Exception:
        respond(False, message="No se pudo decodificar la imagen proporcionada.")
        return

    if modality == "face":
        template = face_template(binary)
    elif modality == "signature":
        template = signature_template(binary)
    else:
        respond(False, message="Modalidad no soportada.")
        return

    respond(True, template=template)


def handle_compare(modality: str, reference: Dict[str, Any], sample: Dict[str, Any]) -> None:
    if modality == "face":
        similarity = face_compare(reference, sample)
    elif modality == "signature":
        similarity = signature_compare(reference, sample)
    else:
        respond(False, message="Modalidad no soportada.")
        return

    if similarity is None:
        respond(False, message="No fue posible comparar las plantillas proporcionadas.")
        return

    respond(True, score=round(max(0.0, min(1.0, similarity)) * 100, 2))


def main() -> None:
    request = load_request()
    action = request.get("action")

    if action == "ping":
        respond(True, message="python-biometric-cli")
        return

    if action == "template":
        modality = request.get("modality")
        binary_b64 = request.get("binary", "")
        if not isinstance(modality, str) or not isinstance(binary_b64, str):
            respond(False, message="Solicitud inválida.")
            return
        handle_template(modality, binary_b64)
        return

    if action == "compare":
        modality = request.get("modality")
        reference = request.get("reference")
        sample = request.get("sample")
        if not isinstance(modality, str) or not isinstance(reference, dict) or not isinstance(sample, dict):
            respond(False, message="Solicitud inválida.")
            return
        handle_compare(modality, reference, sample)
        return

    respond(False, message="Acción no soportada.")


if __name__ == "__main__":
    main()
