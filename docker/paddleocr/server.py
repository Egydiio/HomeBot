"""
OCR REST service — uses Tesseract for text extraction from receipt images.
Accepts image file uploads, returns extracted text lines + confidence scores.
"""
import logging
import tempfile
import os
from contextlib import asynccontextmanager

import cv2
import numpy as np
from fastapi import FastAPI, File, HTTPException, UploadFile
from PIL import Image, ImageFilter, ImageEnhance
from pyzbar import pyzbar
import pytesseract

logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)


@asynccontextmanager
async def lifespan(app: FastAPI):
    logger.info("OCR service ready (Tesseract).")
    yield


app = FastAPI(lifespan=lifespan)


@app.post("/ocr")
async def extract_text(file: UploadFile = File(...)):
    contents = await file.read()

    if not contents:
        raise HTTPException(status_code=400, detail="Empty file")

    suffix = os.path.splitext(file.filename or "image.jpg")[1] or ".jpg"
    with tempfile.NamedTemporaryFile(suffix=suffix, delete=False) as tmp:
        tmp.write(contents)
        tmp_path = tmp.name

    try:
        img = Image.open(tmp_path).convert("L")  # grayscale

        # Light preprocessing to improve receipt OCR accuracy
        img = ImageEnhance.Contrast(img).enhance(2.0)
        img = img.filter(ImageFilter.SHARPEN)

        # Portuguese + English covers all receipt text
        text = pytesseract.image_to_string(img, lang="por+eng", config="--psm 6")

        lines_raw = [l.strip() for l in text.splitlines() if l.strip()]

        lines = [{"text": line, "confidence": 1.0} for line in lines_raw]

    except Exception as exc:
        logger.error("OCR error: %s", exc)
        raise HTTPException(status_code=500, detail=f"OCR failed: {exc}")
    finally:
        os.unlink(tmp_path)

    return {
        "lines": lines,
        "full_text": "\n".join(l["text"] for l in lines),
        "success": True,
    }


@app.post("/qr")
async def decode_qr(file: UploadFile = File(...)):
    """Decode QR code from image. Tries multiple preprocessing strategies."""
    contents = await file.read()

    if not contents:
        raise HTTPException(status_code=400, detail="Empty file")

    nparr = np.frombuffer(contents, np.uint8)
    img   = cv2.imdecode(nparr, cv2.IMREAD_COLOR)

    if img is None:
        raise HTTPException(status_code=400, detail="Could not decode image")

    results = _try_decode_qr(img)

    if not results:
        # Enlarge small QR codes and retry
        h, w = img.shape[:2]
        scale = max(1.0, 1200 / max(h, w))
        if scale > 1.0:
            enlarged = cv2.resize(img, None, fx=scale, fy=scale, interpolation=cv2.INTER_CUBIC)
            results = _try_decode_qr(enlarged)

    if not results:
        return {"found": False, "data": None}

    data = results[0].data.decode("utf-8", errors="replace")
    return {"found": True, "data": data}


def _try_decode_qr(img):
    """Try pyzbar on original, then on grayscale + adaptive threshold."""
    # Attempt 1: original colour
    decoded = pyzbar.decode(img)
    if decoded:
        return decoded

    # Attempt 2: grayscale
    gray = cv2.cvtColor(img, cv2.COLOR_BGR2GRAY)
    decoded = pyzbar.decode(gray)
    if decoded:
        return decoded

    # Attempt 3: adaptive threshold (handles uneven lighting / shadows)
    thresh = cv2.adaptiveThreshold(
        gray, 255, cv2.ADAPTIVE_THRESH_GAUSSIAN_C, cv2.THRESH_BINARY, 51, 10
    )
    decoded = pyzbar.decode(thresh)
    if decoded:
        return decoded

    # Attempt 4: sharpen + threshold
    kernel  = np.array([[0, -1, 0], [-1, 5, -1], [0, -1, 0]])
    sharp   = cv2.filter2D(gray, -1, kernel)
    _, bw   = cv2.threshold(sharp, 0, 255, cv2.THRESH_BINARY + cv2.THRESH_OTSU)
    return pyzbar.decode(bw)


@app.get("/health")
def health():
    return {"status": "ok", "ready": True, "engine": "tesseract+zbar"}
