const express = require('express');
const config = require('./config');
const logger = require('./logger');
const {
  ensureSession,
  getSessionState,
  sendMessage,
  sanitizeSessionName
} = require('./session-manager');

const app = express();

app.use(express.json({ limit: '1mb' }));

app.use((req, res, next) => {
  if (!config.authToken) {
    return next();
  }

  const header = String(req.headers.authorization || '');
  const expected = `Bearer ${config.authToken}`;
  const queryToken = String(req.query.access_token || '');

  if (header !== expected && queryToken !== config.authToken) {
    return res.status(401).json({
      success: false,
      message: 'Nao autorizado.'
    });
  }

  return next();
});

app.get('/health', async (req, res) => {
  const sessionName = sanitizeSessionName(req.query.session || config.defaultSession);

  try {
    if (req.query.autoStart === '1') {
      await ensureSession(sessionName);
    }

    return res.json({
      success: true,
      message: 'Bridge online.',
      session: getSessionState(sessionName)
    });
  } catch (error) {
    return res.status(500).json({
      success: false,
      message: error.message,
      session: getSessionState(sessionName)
    });
  }
});

app.post('/sessions/start', async (req, res) => {
  const sessionName = sanitizeSessionName(req.body.session || config.defaultSession);

  try {
    await ensureSession(sessionName);
    return res.json({
      success: true,
      message: 'Sessao iniciada.',
      session: getSessionState(sessionName)
    });
  } catch (error) {
    return res.status(500).json({
      success: false,
      message: error.message,
      session: getSessionState(sessionName)
    });
  }
});

app.get('/sessions/:session/status', async (req, res) => {
  const sessionName = sanitizeSessionName(req.params.session);

  if (req.query.autoStart === '1') {
    try {
      await ensureSession(sessionName);
    } catch (error) {
      return res.status(500).json({
        success: false,
        message: error.message,
        session: getSessionState(sessionName)
      });
    }
  }

  return res.json({
    success: true,
    session: getSessionState(sessionName)
  });
});

app.get('/sessions/:session/qr', async (req, res) => {
  const sessionName = sanitizeSessionName(req.params.session);

  try {
    await ensureSession(sessionName);
    const state = getSessionState(sessionName);

    if (!state.qr) {
      return res.status(404).json({
        success: false,
        message: 'QR indisponivel no momento.',
        session: state
      });
    }

    return res.json({
      success: true,
      session: state
    });
  } catch (error) {
    return res.status(500).json({
      success: false,
      message: error.message,
      session: getSessionState(sessionName)
    });
  }
});

app.get('/sessions/:session/qr/view', async (req, res) => {
  const sessionName = sanitizeSessionName(req.params.session);

  try {
    await ensureSession(sessionName);
    const state = getSessionState(sessionName);

    if (!state.qr) {
      return res.status(404).send(`<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>QR indisponivel</title>
  <style>
    body { font-family: Arial, sans-serif; background: #111827; color: #f9fafb; display:flex; min-height:100vh; align-items:center; justify-content:center; margin:0; }
    .card { max-width: 560px; padding: 24px; background:#1f2937; border-radius:16px; box-shadow:0 10px 30px rgba(0,0,0,.35); }
    code { background:#111827; padding:2px 6px; border-radius:6px; }
  </style>
</head>
<body>
  <div class="card">
    <h1>QR indisponivel</h1>
    <p>Sessao: <code>${state.session}</code></p>
    <p>Status atual: <code>${state.status}</code></p>
    <p>Se a sessao ainda nao gerou QR, recarregue esta pagina em alguns segundos.</p>
    ${state.lastError ? `<p>Ultimo erro: <code>${String(state.lastError)}</code></p>` : ''}
  </div>
</body>
</html>`);
    }

    return res.send(`<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>QR WhatsApp</title>
  <style>
    body { font-family: Arial, sans-serif; background: #0f172a; color: #e2e8f0; display:flex; min-height:100vh; align-items:center; justify-content:center; margin:0; }
    .card { width:min(92vw, 520px); padding: 24px; background:#111827; border-radius:20px; box-shadow:0 20px 50px rgba(0,0,0,.4); text-align:center; }
    img { width:min(100%, 360px); background:#fff; border-radius:16px; padding:16px; }
    .meta { margin-top:16px; font-size:14px; color:#94a3b8; }
    code { background:#0b1220; padding:2px 6px; border-radius:6px; color:#f8fafc; }
  </style>
</head>
<body>
  <div class="card">
    <h1>Conectar WhatsApp</h1>
    <p>Escaneie este QR com o WhatsApp da sessao <code>${state.session}</code>.</p>
    <img src="${state.qr}" alt="QR Code da sessao ${state.session}">
    <div class="meta">
      <div>Status: <code>${state.status}</code></div>
      <div>Atualizado em: <code>${state.qrUpdatedAt || '-'}</code></div>
    </div>
  </div>
</body>
</html>`);
  } catch (error) {
    return res.status(500).send(`<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Erro ao carregar QR</title>
</head>
<body>
  <h1>Erro ao carregar QR</h1>
  <p>${String(error.message || error)}</p>
</body>
</html>`);
  }
});

app.post('/send', async (req, res) => {
  const sessionName = sanitizeSessionName(req.body.session || config.defaultSession);
  const to = String(req.body.to || '');
  const message = String(req.body.message || '');

  if (!to.trim()) {
    return res.status(422).json({
      success: false,
      message: 'Campo "to" obrigatorio.'
    });
  }

  if (!message.trim()) {
    return res.status(422).json({
      success: false,
      message: 'Campo "message" obrigatorio.'
    });
  }

  try {
    const result = await sendMessage({ sessionName, to, message });
    return res.json({
      success: true,
      message: 'Mensagem enviada com sucesso.',
      session: getSessionState(sessionName),
      data: result
    });
  } catch (error) {
    logger.error('Falha ao enviar mensagem', {
      session: sessionName,
      error: error.message
    });

    return res.status(500).json({
      success: false,
      message: error.message,
      session: getSessionState(sessionName)
    });
  }
});

app.use((req, res) => {
  res.status(404).json({
    success: false,
    message: 'Rota nao encontrada.'
  });
});

app.listen(config.port, config.host, () => {
  logger.info('Bridge iniciado', {
    host: config.host,
    port: config.port,
    defaultSession: config.defaultSession
  });
});
