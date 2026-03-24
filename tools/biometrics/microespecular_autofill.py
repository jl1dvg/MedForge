#!/usr/bin/env python3
from __future__ import annotations

import json
import os
import re
import shutil
import subprocess
import sys
import tempfile
from dataclasses import dataclass
from typing import Any, Dict, List, Optional

try:
    from PIL import Image, ImageOps, ImageEnhance, ImageFilter
except Exception:
    Image = None  # type: ignore
    ImageOps = None  # type: ignore
    ImageEnhance = None  # type: ignore
    ImageFilter = None  # type: ignore


@dataclass
class EyeMetrics:
    eye: str
    densidad: Optional[str] = None
    desviacion: Optional[str] = None
    coef_var: Optional[str] = None
    score: float = 0.0


def load_request() -> Dict[str, Any]:
    raw = sys.stdin.read()
    if not raw.strip():
        return {}
    try:
        return json.loads(raw)
    except json.JSONDecodeError:
        return {}


def respond(payload: Dict[str, Any]) -> None:
    sys.stdout.write(json.dumps(payload, ensure_ascii=False))


def normalize_text(text: str) -> str:
    text = text.upper().replace("\r", "\n")
    text = text.replace("|", "I").replace("§", "S").replace("¢", "C").replace("€", "C")
    text = re.sub(r"[ \t]+", " ", text)
    text = re.sub(r"\n{2,}", "\n", text)
    return text.strip()


def resolve_tesseract_bin() -> Optional[str]:
    env_bin = os.environ.get("TESSERACT_BIN", "").strip()
    if env_bin:
        return env_bin if os.path.isfile(env_bin) or shutil.which(env_bin) else None

    discovered = shutil.which("tesseract")
    if discovered:
        return discovered

    for candidate in (
        "/usr/bin/tesseract",
        "/usr/local/bin/tesseract",
        "/opt/homebrew/bin/tesseract",
    ):
        if os.path.isfile(candidate):
            return candidate

    return None


def tesseract_text(image: "Image.Image", args: List[str]) -> str:
    tesseract_bin = resolve_tesseract_bin()
    if not tesseract_bin:
        raise RuntimeError("No se encontró el binario de tesseract en el servidor.")

    fd, path = tempfile.mkstemp(prefix="microesp_", suffix=".png")
    os.close(fd)
    try:
        image.save(path, format="PNG")
        cmd = [tesseract_bin, path, "stdout", "-l", "eng", *args]
        proc = subprocess.run(cmd, capture_output=True, text=True, check=False)
        return (proc.stdout or "").strip()
    finally:
        try:
            os.unlink(path)
        except FileNotFoundError:
            pass


def score_text(text: str) -> int:
    normalized = normalize_text(text)
    score = 0
    for needle in ["CELL DENSITY", "STANDARD DEVIATION", "COEFFICIENT OF VARIATION"]:
        if needle in normalized:
            score += 2
    if re.search(r"\bR\b", normalized) or re.search(r"\bL\b", normalized):
        score += 1
    score += len(re.findall(r"\b\d{2,5}\b", normalized))
    return score


def prepare_for_ocr(image: "Image.Image", scale: int = 2) -> "Image.Image":
    gray = ImageOps.grayscale(image)
    width, height = gray.size
    resized = gray.resize((max(1, width * scale), max(1, height * scale)))
    contrasted = ImageEnhance.Contrast(resized).enhance(2.2)
    bright = ImageEnhance.Brightness(contrasted).enhance(1.15)
    sharp = bright.filter(ImageFilter.SHARPEN)
    return sharp


def crop_ratio(image: "Image.Image", x: float, y: float, w: float, h: float) -> "Image.Image":
    width, height = image.size
    left = max(0, min(width - 1, int(width * x)))
    top = max(0, min(height - 1, int(height * y)))
    right = max(left + 1, min(width, left + int(width * w)))
    bottom = max(top + 1, min(height, top + int(height * h)))
    return image.crop((left, top, right, bottom))


def build_segments(image: "Image.Image") -> List["Image.Image"]:
    width, height = image.size
    ratio = height / max(width, 1)
    if ratio >= 1.45:
        return [
            crop_ratio(image, 0.0, 0.0, 1.0, 0.52),
            crop_ratio(image, 0.0, 0.48, 1.0, 0.52),
        ]
    return [image.copy()]


def detect_eye(text: str) -> Optional[str]:
    sample = "\n".join(normalize_text(text).splitlines()[:4])
    if re.search(r"\bR\s*(?:\(|$)", sample) or re.search(r"\bR\b", sample):
        return "OD"
    if re.search(r"\bL\s*(?:\(|$)", sample) or re.search(r"\bL\b", sample):
        return "OI"
    return None


def extract_metric(line_text: str, labels: List[str]) -> Optional[str]:
    lines = normalize_text(line_text).splitlines()
    for line in lines:
        for label in labels:
            if label not in line:
                continue
            numbers = re.findall(r"\d{1,5}", line)
            if numbers:
                return numbers[-1]
    text = normalize_text(line_text)
    for label in labels:
        match = re.search(re.escape(label) + r".{0,40}?(\d{1,5})", text, re.S)
        if match:
            return match.group(1)
    return None


def parse_segment(segment: "Image.Image") -> Dict[str, Any]:
    eye_crop = crop_ratio(segment, 0.12, 0.00, 0.34, 0.22)
    table_crop = crop_ratio(segment, 0.36, 0.00, 0.64, 0.72)

    eye_text = tesseract_text(
        prepare_for_ocr(eye_crop, 3),
        ["--psm", "7", "-c", "tessedit_char_whitelist=RL0123456789()"],
    )
    table_attempts = []
    for args in (["--psm", "6"], ["--psm", "11"], ["--psm", "4"]):
        table_attempts.append(tesseract_text(prepare_for_ocr(table_crop, 2), args))
    table_text = max(table_attempts, key=score_text, default="")

    if not eye_text:
        eye_text = tesseract_text(
            prepare_for_ocr(segment, 2),
            ["--psm", "11", "-c", "tessedit_char_whitelist=RL0123456789()"],
        )
    if not table_text:
        full_attempts = []
        for args in (["--psm", "6"], ["--psm", "11"]):
            full_attempts.append(tesseract_text(prepare_for_ocr(segment, 2), args))
        table_text = max(full_attempts, key=score_text, default="")

    eye = detect_eye(eye_text or table_text)
    densidad = extract_metric(table_text, ["CELL DENSITY", "CELLDENSITY", "CD"])
    desviacion = extract_metric(table_text, ["STANDARD DEVIATION", "STANDARDDEVIATION", "SD"])
    coef_var = extract_metric(table_text, ["COEFFICIENT OF VARIATION", "COEFFICIENTOFVARIATION", "CV"])

    found = sum(1 for value in [densidad, desviacion, coef_var] if value)
    parsed = None
    if eye and found:
        parsed = EyeMetrics(
            eye=eye,
            densidad=densidad,
            desviacion=desviacion,
            coef_var=coef_var,
            score=min(1.0, (found / 3.0) + 0.1),
        )

    return {
        "eye_text": eye_text,
        "table_text": table_text,
        "parsed": None if parsed is None else {
            "eye": parsed.eye,
            "densidad": parsed.densidad,
            "desviacion": parsed.desviacion,
            "coefVar": parsed.coef_var,
            "score": parsed.score,
        },
    }


def process_file(path: str) -> Dict[str, Any]:
    if Image is None:
        return {"error": "Pillow no está disponible."}

    image = Image.open(path)
    image = ImageOps.exif_transpose(image).convert("RGB")
    debug = []
    eyes: Dict[str, Dict[str, Any]] = {}

    for index, segment in enumerate(build_segments(image)):
        result = parse_segment(segment)
        result["segment"] = index
        debug.append(result)
        parsed = result.get("parsed")
        if not isinstance(parsed, dict):
            continue
        eye = parsed.get("eye")
        if eye not in ("OD", "OI"):
            continue
        if eye not in eyes or float(parsed.get("score") or 0) > float(eyes[eye].get("score") or 0):
            eyes[eye] = parsed

    return {"debug": debug, "eyes": eyes}


def build_payload(eyes: Dict[str, Dict[str, Any]]) -> Dict[str, str]:
    payload: Dict[str, str] = {}
    for eye, values in eyes.items():
        suffix = "OI" if eye == "OI" else "OD"
        mapping = {
            "densidad": f"densidad{suffix}",
            "desviacion": f"desviacion{suffix}",
            "coefVar": f"coefVar{suffix}",
        }
        for source_key, target_key in mapping.items():
            value = (values.get(source_key) or "").strip()
            if value:
                payload[target_key] = value
    return payload


def main() -> None:
    try:
        request = load_request()
        files = request.get("files")
        if not isinstance(files, list) or not files:
            respond({"success": False, "error": "No se recibieron archivos para procesar."})
            return

        attempts = []
        merged_eyes: Dict[str, Dict[str, Any]] = {}
        files_used: List[str] = []

        for item in files:
            if not isinstance(item, dict):
                continue
            path = str(item.get("path") or "").strip()
            name = str(item.get("name") or os.path.basename(path)).strip()
            if not path or not os.path.isfile(path):
                continue

            result = process_file(path)
            attempts.append({"file": name, "debug": result.get("debug", [])})
            eyes = result.get("eyes", {})
            if isinstance(eyes, dict) and eyes:
                files_used.append(name)
                for eye, parsed in eyes.items():
                    if eye not in merged_eyes or float(parsed.get("score") or 0) > float(merged_eyes[eye].get("score") or 0):
                        merged_eyes[eye] = parsed

        payload = build_payload(merged_eyes)
        warnings = []
        if not {"densidadOD", "desviacionOD", "coefVarOD"} <= payload.keys():
            warnings.append("No se pudo completar automáticamente el ojo derecho.")
        if not {"densidadOI", "desviacionOI", "coefVarOI"} <= payload.keys():
            warnings.append("No se pudo completar automáticamente el ojo izquierdo.")

        respond({
            "success": bool(payload),
            "payload": payload,
            "warnings": warnings,
            "files_used": files_used,
            "attempts": attempts,
            "error": "" if payload else "No se pudieron extraer valores de microscopía especular desde las imágenes disponibles.",
        })
    except Exception as exc:
        respond({
            "success": False,
            "payload": {},
            "warnings": [],
            "files_used": [],
            "attempts": [],
            "error": str(exc),
        })


if __name__ == "__main__":
    main()
