const fs = require('fs');
const path = require('path');
const QRCode = require('qrcode');
const { Client, LocalAuth } = require('whatsapp-web.js');
const config = require('./config');
const logger = require('./logger');

fs.mkdirSync(config.sessionDataDir, { recursive: true });

const sessions = new Map();

function sanitizeSessionName(value) {
  const normalized = String(value || '')
    .trim()
    .replace(/[^a-zA-Z0-9_-]/g, '-')
    .replace(/-+/g, '-')
    .replace(/^-|-$/g, '');

  return normalized || config.defaultSession;
}

function normalizePhone(value) {
  const digits = String(value || '').replace(/\D+/g, '');
  if (!digits) {
    return '';
  }

  if (digits.length === 10 || digits.length === 11) {
    return `55${digits}`;
  }

  if (digits.length < 12 || digits.length > 15) {
    return '';
  }

  return digits;
}

function buildClient(sessionName) {
  const authStrategy = new LocalAuth({
    clientId: sessionName,
    dataPath: config.sessionDataDir
  });

  const puppeteer = {
    headless: config.puppeteerHeadless,
    args: [
      '--no-sandbox',
      '--disable-setuid-sandbox',
      '--disable-dev-shm-usage',
      '--disable-gpu'
    ]
  };

  if (config.puppeteerExecutablePath) {
    puppeteer.executablePath = config.puppeteerExecutablePath;
  }

  return new Client({
    authStrategy,
    puppeteer
  });
}

async function ensureSession(sessionNameInput) {
  const sessionName = sanitizeSessionName(sessionNameInput);
  const existing = sessions.get(sessionName);

  if (existing) {
    if (!existing.initializingPromise) {
      existing.initializingPromise = Promise.resolve(existing);
    }
    return existing.initializingPromise;
  }

  const session = {
    name: sessionName,
    client: buildClient(sessionName),
    status: 'initializing',
    qr: null,
    qrUpdatedAt: null,
    lastReadyAt: null,
    lastError: null,
    initializingPromise: null
  };

  session.client.on('qr', async (qr) => {
    session.status = 'qr_ready';
    session.lastError = null;
    session.qrUpdatedAt = new Date().toISOString();
    try {
      session.qr = await QRCode.toDataURL(qr);
    } catch (error) {
      session.qr = null;
      session.lastError = `Falha ao gerar QR em base64: ${error.message}`;
    }
    logger.info('QR atualizado', { session: sessionName });
  });

  session.client.on('ready', () => {
    session.status = 'ready';
    session.qr = null;
    session.lastError = null;
    session.lastReadyAt = new Date().toISOString();
    logger.info('Sessao pronta', { session: sessionName });
  });

  session.client.on('authenticated', () => {
    session.status = 'authenticated';
    session.lastError = null;
    logger.info('Sessao autenticada', { session: sessionName });
  });

  session.client.on('auth_failure', (message) => {
    session.status = 'auth_failure';
    session.lastError = String(message || 'Falha de autenticacao.');
    logger.error('Falha de autenticacao', { session: sessionName, message: session.lastError });
  });

  session.client.on('disconnected', (reason) => {
    session.status = 'disconnected';
    session.lastError = String(reason || 'Sessao desconectada.');
    logger.warn('Sessao desconectada', { session: sessionName, reason: session.lastError });
  });

  session.client.on('change_state', (state) => {
    session.status = String(state || 'unknown').toLowerCase();
    logger.debug('Estado alterado', { session: sessionName, state });
  });

  sessions.set(sessionName, session);

  session.initializingPromise = session.client.initialize()
    .then(() => session)
    .catch((error) => {
      session.status = 'error';
      session.lastError = error.message;
      logger.error('Erro ao inicializar sessao', { session: sessionName, error: error.message });
      throw error;
    });

  return session.initializingPromise;
}

function getSessionState(sessionNameInput) {
  const sessionName = sanitizeSessionName(sessionNameInput);
  const session = sessions.get(sessionName);

  if (!session) {
    return {
      exists: false,
      session: sessionName,
      status: 'not_started',
      qr: null,
      qrUpdatedAt: null,
      lastReadyAt: null,
      lastError: null
    };
  }

  return {
    exists: true,
    session: sessionName,
    status: session.status,
    qr: session.qr,
    qrUpdatedAt: session.qrUpdatedAt,
    lastReadyAt: session.lastReadyAt,
    lastError: session.lastError
  };
}

async function sendMessage({ sessionName, to, message }) {
  const session = await ensureSession(sessionName);

  if (session.status !== 'ready') {
    throw new Error(`Sessao ${session.name} ainda nao esta pronta. Estado atual: ${session.status}.`);
  }

  const normalizedPhone = normalizePhone(to);
  if (!normalizedPhone) {
    throw new Error('Numero de destino invalido.');
  }

  const chatId = `${normalizedPhone}@c.us`;
  const result = await session.client.sendMessage(chatId, String(message || ''));

  return {
    id: result?.id?._serialized || null,
    to: normalizedPhone,
    chatId,
    timestamp: new Date().toISOString()
  };
}

module.exports = {
  ensureSession,
  getSessionState,
  sendMessage,
  sanitizeSessionName,
  normalizePhone
};
