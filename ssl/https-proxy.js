/**
 * Simple HTTPS → HTTP reverse proxy for local phone camera testing.
 * Frontend: https://0.0.0.0:8443  →  PHP: http://127.0.0.1:8080
 */
const https = require('https');
const http = require('http');
const fs = require('fs');
const path = require('path');

const CERT_DIR = path.join(__dirname, 'certs');
const KEY = path.join(CERT_DIR, 'ai-shop.key');
const CRT = path.join(CERT_DIR, 'ai-shop.crt');
const LISTEN_PORT = Number(process.env.HTTPS_PORT || 8443);
const TARGET_HOST = process.env.TARGET_HOST || '127.0.0.1';
const TARGET_PORT = Number(process.env.TARGET_PORT || 8080);

if (!fs.existsSync(KEY) || !fs.existsSync(CRT)) {
  console.error('Missing SSL certs. Run: powershell -File ssl/make-cert.ps1');
  process.exit(1);
}

const tls = {
  key: fs.readFileSync(KEY),
  cert: fs.readFileSync(CRT),
};

const server = https.createServer(tls, (req, res) => {
  // Keep the browser Host so PHP builds correct absolute URLs when needed.
  // Assets use root-relative paths; forwarded headers still mark HTTPS.
  const headers = {
    ...req.headers,
    'x-forwarded-proto': 'https',
    'x-forwarded-host': req.headers.host || `0.0.0.0:${LISTEN_PORT}`,
    'x-forwarded-port': String(LISTEN_PORT),
  };

  const proxyReq = http.request(
    {
      hostname: TARGET_HOST,
      port: TARGET_PORT,
      path: req.url,
      method: req.method,
      headers,
    },
    (proxyRes) => {
      // Avoid mixed-content / caching surprises on phones
      const outHeaders = { ...proxyRes.headers };
      delete outHeaders['content-security-policy'];
      res.writeHead(proxyRes.statusCode || 502, outHeaders);
      proxyRes.pipe(res);
    }
  );

  proxyReq.on('error', (err) => {
    console.error('Proxy error:', err.message);
    if (!res.headersSent) {
      res.writeHead(502, { 'Content-Type': 'text/plain; charset=utf-8' });
    }
    res.end('Bad gateway: start PHP with  php -S 0.0.0.0:8080');
  });

  req.pipe(proxyReq);
});

server.listen(LISTEN_PORT, '0.0.0.0', () => {
  console.log(`HTTPS proxy listening on https://0.0.0.0:${LISTEN_PORT}`);
  console.log(`Forwarding to http://${TARGET_HOST}:${TARGET_PORT}`);
});
