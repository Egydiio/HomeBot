FROM python:3.11-slim

RUN apt-get update && apt-get install -y --no-install-recommends \
        tesseract-ocr \
        tesseract-ocr-por \
        tesseract-ocr-eng \
        libglib2.0-0 \
        libzbar0 \
        zbar-tools \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

RUN pip install --no-cache-dir \
        pytesseract \
        pyzbar \
        fastapi \
        "uvicorn[standard]" \
        python-multipart \
        Pillow \
        opencv-python-headless

WORKDIR /app
COPY server.py .

EXPOSE 8866

HEALTHCHECK --interval=15s --timeout=5s --start-period=10s --retries=3 \
    CMD python -c "import urllib.request; urllib.request.urlopen('http://localhost:8866/health')"

CMD ["uvicorn", "server:app", "--host", "0.0.0.0", "--port", "8866", "--workers", "1"]
