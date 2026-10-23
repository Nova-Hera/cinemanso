/*
 * Emoji customizado do Neko (sidecar). Sem dependências: roda em node:22-alpine.
 *
 * O Caddy manda /custom-emoji/* do host do Neko para cá; o resto vai para o Neko. Assim o navegador fala
 * com a mesma origem do Neko e o cookie de sessão do Neko chega junto — é ele que prova que quem sobe/remove
 * é admin: repassamos o cookie para GET /api/whoami do próprio Neko e exigimos profile.is_admin.
 *
 *   GET    /custom-emoji/api/list           -> [{ name, v }]            (v = mtime, para cache-bust)
 *   GET    /custom-emoji/img/<nome>.png     -> imagem
 *   PUT    /custom-emoji/api/emoji/<nome>   -> corpo = PNG cru (admin)
 *   DELETE /custom-emoji/api/emoji/<nome>   -> remove (admin)
 */
'use strict';

const http = require('http');
const fs = require('fs');
const path = require('path');

const PORT = Number(process.env.PORT || 3000);
const DATA_DIR = process.env.DATA_DIR || '/data';
const NEKO_URL = (process.env.NEKO_URL || 'http://neko:8080').replace(/\/$/, '');

const NAME_RE = /^[a-z0-9_]{2,32}$/;
const MAX_BYTES = 128 * 1024;
const MAX_DIM = 256;
const MIN_DIM = 16;
const MAX_COUNT = 500;
const PNG_SIGNATURE = Buffer.from([0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a]);

fs.mkdirSync(DATA_DIR, { recursive: true });

const file = (name) => path.join(DATA_DIR, `${name}.png`);

function send(res, status, body, headers = {}) {
  const isJson = body !== undefined && typeof body === 'object' && !Buffer.isBuffer(body);
  const payload = isJson ? JSON.stringify(body) : body;

  res.writeHead(status, {
    'X-Content-Type-Options': 'nosniff',
    ...(isJson ? { 'Content-Type': 'application/json; charset=utf-8' } : {}),
    ...headers,
  });
  res.end(payload);
}

function list() {
  return fs
    .readdirSync(DATA_DIR)
    .filter((f) => f.endsWith('.png') && NAME_RE.test(f.slice(0, -4)))
    .map((f) => ({ name: f.slice(0, -4), v: Math.floor(fs.statSync(path.join(DATA_DIR, f)).mtimeMs / 1000) }))
    .sort((a, b) => a.name.localeCompare(b.name));
}

/** Quem chama é admin no Neko? Repassa cookie/Authorization para /api/whoami do Neko. */
async function isAdmin(req) {
  const headers = {};

  if (req.headers.cookie) headers.cookie = req.headers.cookie;
  if (req.headers.authorization) headers.authorization = req.headers.authorization;
  if (!headers.cookie && !headers.authorization) return { status: 401 };

  let response;

  try {
    response = await fetch(`${NEKO_URL}/api/whoami`, { headers, signal: AbortSignal.timeout(5000) });
  } catch {
    return { status: 502 };
  }

  if (response.status === 401 || response.status === 403) return { status: 401 };
  if (!response.ok) return { status: 502 };

  const me = await response.json().catch(() => null);

  return me && me.profile && me.profile.is_admin === true ? { status: 200 } : { status: 403 };
}

/** Navegadores mandam Origin em PUT/DELETE; se vier de outro site, recusa (o PUT já exige image/png, sem preflight liberado). */
function sameOrigin(req) {
  const origin = req.headers.origin;

  if (!origin) return true;

  try {
    return new URL(origin).host === req.headers.host;
  } catch {
    return false;
  }
}

function readBody(req, limit) {
  return new Promise((resolve, reject) => {
    const chunks = [];
    let size = 0;
    let rejected = false;

    req.on('data', (chunk) => {
      if (rejected) return;

      size += chunk.length;

      if (size > limit) {
        // responde 413 e fecha a conexão (Connection: close no catch); o resto do corpo é descartado
        rejected = true;
        chunks.length = 0;
        reject(Object.assign(new Error('too large'), { status: 413 }));

        return;
      }

      chunks.push(chunk);
    });
    req.on('end', () => resolve(Buffer.concat(chunks)));
    req.on('error', reject);
  });
}

/** PNG válido (assinatura + IHDR), quadrado e dentro dos limites. */
function checkPng(buffer) {
  if (buffer.length < 33 || !buffer.subarray(0, 8).equals(PNG_SIGNATURE)) return 'not a PNG';
  if (buffer.toString('ascii', 12, 16) !== 'IHDR') return 'not a PNG';

  const width = buffer.readUInt32BE(16);
  const height = buffer.readUInt32BE(20);

  if (width !== height) return 'image must be square';
  if (width < MIN_DIM || width > MAX_DIM) return `image must be ${MIN_DIM}-${MAX_DIM}px`;

  return null;
}

const server = http.createServer(async (req, res) => {
  const url = new URL(req.url, 'http://localhost');
  const route = url.pathname;

  try {
    if (req.method === 'GET' && route === '/custom-emoji/api/list') {
      return send(res, 200, list(), { 'Cache-Control': 'no-store' });
    }

    let match = route.match(/^\/custom-emoji\/img\/([a-z0-9_]{2,32})\.png$/);

    if (req.method === 'GET' && match) {
      let data;

      try {
        data = fs.readFileSync(file(match[1]));
      } catch {
        return send(res, 404, { error: 'not found' });
      }

      return send(res, 200, data, { 'Content-Type': 'image/png', 'Cache-Control': 'public, max-age=31536000, immutable' });
    }

    match = route.match(/^\/custom-emoji\/api\/emoji\/([^/]+)$/);

    if (match && (req.method === 'PUT' || req.method === 'DELETE')) {
      const name = decodeURIComponent(match[1]);

      if (!NAME_RE.test(name)) return send(res, 400, { error: 'invalid name' });
      if (!sameOrigin(req)) return send(res, 403, { error: 'forbidden' });

      const auth = await isAdmin(req);

      if (auth.status === 401) return send(res, 401, { error: 'not logged in' });
      if (auth.status === 403) return send(res, 403, { error: 'admins only' });
      if (auth.status !== 200) return send(res, 502, { error: 'could not reach Neko' });

      if (req.method === 'DELETE') {
        try {
          fs.unlinkSync(file(name));
        } catch {
          return send(res, 404, { error: 'not found' });
        }

        return send(res, 204);
      }

      if (!/^image\/png\b/.test(req.headers['content-type'] || '')) return send(res, 415, { error: 'send image/png' });

      const body = await readBody(req, MAX_BYTES);
      const problem = checkPng(body);

      if (problem) return send(res, 400, { error: problem });
      if (!fs.existsSync(file(name)) && list().length >= MAX_COUNT) return send(res, 409, { error: 'too many emoji' });

      const tmp = file(`${name}.tmp${process.pid}`);

      fs.writeFileSync(tmp, body);
      fs.renameSync(tmp, file(name));

      return send(res, 200, { name, v: Math.floor(fs.statSync(file(name)).mtimeMs / 1000) });
    }

    return send(res, 404, { error: 'not found' });
  } catch (error) {
    return send(res, error.status || 500, { error: error.status === 413 ? 'file too large' : 'server error' }, error.status === 413 ? { Connection: 'close' } : {});
  }
});

server.listen(PORT, () => console.log(`neko-emoji listening on :${PORT}, data in ${DATA_DIR}`));
