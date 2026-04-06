const config = require('./config');

const levels = ['error', 'warn', 'info', 'debug'];
const currentIndex = levels.indexOf(config.logLevel) >= 0 ? levels.indexOf(config.logLevel) : 2;

function log(level, message, meta) {
  if (levels.indexOf(level) > currentIndex) {
    return;
  }

  const parts = [
    new Date().toISOString(),
    level.toUpperCase(),
    message
  ];

  if (meta !== undefined) {
    parts.push(typeof meta === 'string' ? meta : JSON.stringify(meta));
  }

  console.log(parts.join(' | '));
}

module.exports = {
  error(message, meta) {
    log('error', message, meta);
  },
  warn(message, meta) {
    log('warn', message, meta);
  },
  info(message, meta) {
    log('info', message, meta);
  },
  debug(message, meta) {
    log('debug', message, meta);
  }
};
