require("dotenv").config();

const express = require("express");
const axios = require("axios");
const qrcode = require("qrcode-terminal");
const { Client, LocalAuth } = require("whatsapp-web.js");

const port = Number(process.env.PORT || 3000);
const webhookUrl = process.env.LARAVEL_WEBHOOK_URL;
const serviceToken = process.env.WHATSAPP_SERVICE_TOKEN || "";
const webhookToken = process.env.WHATSAPP_WEBHOOK_TOKEN || "";
const ignoreGroupMessages = String(process.env.IGNORE_GROUP_MESSAGES || "true") === "true";
const maxMediaBytes = Number(process.env.MAX_MEDIA_BYTES || 10 * 1024 * 1024);
const webhookRetryAttempts = Number(process.env.WEBHOOK_RETRY_ATTEMPTS || 3);
const webhookRetryDelayMs = Number(process.env.WEBHOOK_RETRY_DELAY_MS || 1500);
const puppeteerExecutablePath = process.env.PUPPETEER_EXECUTABLE_PATH || undefined;

let whatsappReady = false;

const webhookClient = axios.create({
  timeout: 15000,
  headers: {
    "Content-Type": "application/json",
    "X-HomeBot-Webhook-Token": webhookToken
  }
});

const app = express();
app.use(express.json({ limit: "12mb" }));

const client = new Client({
  authStrategy: new LocalAuth({
    dataPath: ".wwebjs_auth"
  }),
  puppeteer: {
    headless: true,
    executablePath: puppeteerExecutablePath,
    args: [
      "--no-sandbox",
      "--disable-setuid-sandbox",
      "--disable-dev-shm-usage"
    ]
  },
  webVersionCache: {
    type: "local",
    path: ".wwebjs_cache"
  }
});

function log(message, extra) {
  if (extra) {
    console.log(`[whatsapp-service] ${message}`, extra);
    return;
  }

  console.log(`[whatsapp-service] ${message}`);
}

function normalizePhone(phone) {
  const digits = String(phone || "").replace(/\D+/g, "");

  if (!digits) {
    return null;
  }

  return digits;
}

function toChatId(phone) {
  const normalized = normalizePhone(phone);

  return normalized ? `${normalized}@c.us` : null;
}

function isAuthorized(request) {
  const authorization = request.header("Authorization") || "";
  return authorization === `Bearer ${serviceToken}`;
}

async function delay(ms) {
  return new Promise((resolve) => setTimeout(resolve, ms));
}

async function sendWebhookWithRetry(payload) {
  let lastError = null;

  for (let attempt = 1; attempt <= webhookRetryAttempts; attempt += 1) {
    try {
      await webhookClient.post(webhookUrl, payload);
      return;
    } catch (error) {
      lastError = error;
      const status = error.response ? error.response.status : "no-response";
      log(`Falha ao enviar webhook para o Laravel (tentativa ${attempt}/${webhookRetryAttempts})`, { status });

      if (attempt < webhookRetryAttempts) {
        await delay(webhookRetryDelayMs * attempt);
      }
    }
  }

  log("Webhook descartado após esgotar tentativas", {
    error: lastError ? lastError.message : "unknown"
  });
}

async function buildIncomingPayload(message) {
  const contact = await message.getContact();
  const phone = normalizePhone(message.from);
  const messageType = message.type === "chat" ? "text" : message.type;
  const payload = {
    from: message.from,
    phone,
    name: contact.name || contact.pushname || null,
    pushName: contact.pushname || null,
    body: message.body || "",
    messageType,
    fromMe: Boolean(message.fromMe),
    isGroup: message.from.endsWith("@g.us"),
    timestamp: message.timestamp || Math.floor(Date.now() / 1000)
  };

  if (message.hasMedia) {
    try {
      const media = await message.downloadMedia();

      if (media && Buffer.byteLength(media.data, "base64") <= maxMediaBytes) {
        payload.media = {
          mimeType: media.mimetype || null,
          filename: media.filename || null,
          base64: media.data,
          size: Buffer.byteLength(media.data, "base64")
        };
      } else if (media) {
        log("Mídia ignorada por exceder o tamanho máximo", {
          bytes: Buffer.byteLength(media.data, "base64")
        });
      }
    } catch (error) {
      log("Falha ao baixar mídia recebida", { error: error.message });
    }
  }

  return payload;
}

app.get("/health", (request, response) => {
  response.json({
    status: "ok",
    whatsappReady
  });
});

app.post("/send-message", async (request, response) => {
  if (!isAuthorized(request)) {
    return response.status(401).json({ success: false, error: "unauthorized" });
  }

  const phone = normalizePhone(request.body.phone);
  const message = typeof request.body.message === "string" ? request.body.message.trim() : "";

  if (!phone || !message) {
    return response.status(422).json({ success: false, error: "invalid_payload" });
  }

  if (!whatsappReady) {
    return response.status(503).json({ success: false, error: "whatsapp_not_ready" });
  }

  try {
    const result = await client.sendMessage(toChatId(phone), message);

    return response.json({
      success: true,
      id: result.id ? result.id._serialized : null
    });
  } catch (error) {
    log("Falha ao enviar mensagem", { error: error.message });
    return response.status(500).json({ success: false, error: "send_failed" });
  }
});

client.on("qr", (qr) => {
  whatsappReady = false;
  log("QR recebido");
  qrcode.generate(qr, { small: true });
});

client.on("authenticated", () => {
  log("Cliente autenticado");
});

client.on("ready", () => {
  whatsappReady = true;
  log("Cliente pronto");
});

client.on("auth_failure", (message) => {
  whatsappReady = false;
  log("Erro de autenticação", { message });
});

client.on("disconnected", (reason) => {
  whatsappReady = false;
  log("Cliente desconectado", { reason });
});

client.on("message", async (message) => {
  try {
    if (message.fromMe) {
      return;
    }

    if (ignoreGroupMessages && message.from.endsWith("@g.us")) {
      return;
    }

    if (!message.from.endsWith("@c.us")) {
      return;
    }

    const payload = await buildIncomingPayload(message);
    await sendWebhookWithRetry(payload);
  } catch (error) {
    log("Falha ao processar mensagem recebida", { error: error.message });
  }
});

app.listen(port, () => {
  log(`HTTP server ouvindo na porta ${port}`);
});

if (!webhookUrl) {
  throw new Error("LARAVEL_WEBHOOK_URL não configurada");
}

if (!serviceToken) {
  throw new Error("WHATSAPP_SERVICE_TOKEN não configurado");
}

if (!webhookToken) {
  throw new Error("WHATSAPP_WEBHOOK_TOKEN não configurado");
}

client.initialize().catch((error) => {
  log("Falha ao inicializar cliente WhatsApp", { error: error.message });
});
