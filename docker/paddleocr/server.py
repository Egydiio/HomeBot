"""
PaddleOCR REST service — wraps PaddleOCR with a minimal FastAPI endpoint.
Accepts image file uploads, returns extracted text lines + confidence scores.
"""
import io
import logging
from contextlib import asynccontextmanager

from fastapi import FastAPI, File, HTTPException, UploadFile
from paddleocr import PaddleOCR

logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

ocr_engine: PaddleOCR | None = None


@asynccontextmanager
async def lifespan(app: FastAPI):
    global ocr_engine
    logger.info("Initializing PaddleOCR engine (may download models on first run)...")
    ocr_engine = PaddleOCR(
        use_angle_cls=True,
        lang="en",        # English/Latin covers Portuguese characters
        use_gpu=False,
        show_log=False,
        ocr_version="PP-OCRv4",
    )
    logger.info("PaddleOCR engine ready.")
    yield


app = FastAPI(lifespan=lifespan)


@app.post("/ocr")
async def extract_text(file: UploadFile = File(...)):
    contents = await file.read()

    if not contents:
        raise HTTPException(status_code=400, detail="Empty file")

    try:
        result = ocr_engine.ocr(contents, cls=True)
    except Exception as exc:
        logger.error("OCR inference error: %s", exc)
        raise HTTPException(status_code=500, detail=f"OCR inference failed: {exc}")

    page = result[0] if result else None

    if not page:
        return {"lines": [], "full_text": "", "success": True}

    lines = [
        {"text": line[1][0], "confidence": round(float(line[1][1]), 4)}
        for line in page
        if line and len(line) >= 2
    ]

    return {
        "lines": lines,
        "full_text": "\n".join(l["text"] for l in lines),
        "success": True,
    }


@app.get("/health")
def health():
    ready = ocr_engine is not None
    return {"status": "ok" if ready else "initializing", "ready": ready}
