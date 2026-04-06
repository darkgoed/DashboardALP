# WhatsApp Bridge

Bridge HTTP para integrar o painel PHP com `whatsapp-web.js`.

## Endpoints

- `GET /health`
- `POST /sessions/start`
- `GET /sessions/:session/status`
- `GET /sessions/:session/qr`
- `POST /send`

## Setup

1. Entre em `whatsapp-bridge`
2. Rode `cp .env.example .env`
3. Ajuste `BRIDGE_AUTH_TOKEN`
4. Rode `npm install`
5. Rode `npm start`

## Exemplo de envio

```bash
curl -X POST http://127.0.0.1:3010/send \
  -H "Authorization: Bearer SEU_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "session": "alp-relatorios",
    "to": "5511999999999",
    "message": "Teste de envio"
  }'
```

## Fluxo da primeira sessão

1. Inicie a sessão com `POST /sessions/start`
2. Consulte `GET /sessions/alp-relatorios/qr`
3. Escaneie o QR no WhatsApp
4. Confirme em `GET /sessions/alp-relatorios/status` que o estado ficou `ready`

## Observações

- Use uma VPS com Chrome/Chromium disponível.
- Se precisar, configure `PUPPETEER_EXECUTABLE_PATH` no `.env`.
- As sessões ficam em `whatsapp-bridge/storage/sessions`.

## Dependências do sistema

No teste local, o bridge subiu, mas a sessão falhou ao abrir o navegador porque faltou `libatk-1.0.so.0`.

Em Ubuntu/Debian, instale pelo menos:

```bash
apt-get update
apt-get install -y \
  ca-certificates fonts-liberation libasound2 libatk-bridge2.0-0 libatk1.0-0 \
  libcups2 libdbus-1-3 libgbm1 libgtk-3-0 libnspr4 libnss3 libx11-6 libx11-xcb1 \
  libxcb1 libxcomposite1 libxdamage1 libxext6 libxfixes3 libxrandr2 xdg-utils
```

Se preferir usar o Chrome/Chromium do sistema, aponte `PUPPETEER_EXECUTABLE_PATH` para o binário instalado.
