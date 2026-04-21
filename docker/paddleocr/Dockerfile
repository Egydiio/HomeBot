FROM python:3.11-slim

RUN apt-get update && apt-get install -y --no-install-recommends \
        libglib2.0-0 \
        libsm6 \
        libxext6 \
        libxrender1 \
        libgomp1 \
        libgl1 \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# CPU-only PaddleOCR — significantly smaller than GPU variant
RUN pip install --no-cache-dir \
        paddlepaddle==2.6.2 \
        "paddleocr>=2.8.1" \
        fastapi \
        "uvicorn[standard]" \
        python-multipart \
        Pillow

WORKDIR /app
COPY server.py .

# Pre-download models at build time so first request is fast
RUN python -c "from paddleocr import PaddleOCR; PaddleOCR(use_angle_cls=True, lang='en', use_gpu=False, show_log=False)"

EXPOSE 8866

HEALTHCHECK --interval=15s --timeout=5s --start-period=30s --retries=3 \
    CMD python -c "import urllib.request; urllib.request.urlopen('http://localhost:8866/health')"

CMD ["uvicorn", "server:app", "--host", "0.0.0.0", "--port", "8866", "--workers", "1"]
