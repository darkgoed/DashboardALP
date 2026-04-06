const path = require('path');
const dotenv = require('dotenv');

dotenv.config();

function env(name, fallback = '') {
  const value = process.env[name];
  return value === undefined ? fallback : String(value);
}

function envBool(name, fallback = true) {
  const value = env(name, fallback ? 'true' : 'false').toLowerCase();
  return ['1', 'true', 'yes', 'on'].includes(value);
}

module.exports = {
  port: Number(env('PORT', '3010')) || 3010,
  host: env('HOST', '0.0.0.0'),
  authToken: env('BRIDGE_AUTH_TOKEN', ''),
  defaultSession: env('DEFAULT_SESSION', 'alp-relatorios'),
  sessionDataDir: path.resolve(process.cwd(), env('SESSION_DATA_DIR', './storage/sessions')),
  logLevel: env('LOG_LEVEL', 'info'),
  puppeteerExecutablePath: env('PUPPETEER_EXECUTABLE_PATH', ''),
  puppeteerHeadless: envBool('PUPPETEER_HEADLESS', true)
};
