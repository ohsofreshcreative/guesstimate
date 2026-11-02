// Żądania przeglądarki → php-cgi (bez nasłuchującego serwera) lub pliki statyczne z dysku.
import { spawn } from 'node:child_process';
import { readFile } from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../../../../..');
const PREPEND = path.join(path.dirname(fileURLToPath(import.meta.url)), 'cgi-prepend.php');
const MIME = { '.css': 'text/css', '.js': 'text/javascript', '.png': 'image/png', '.jpg': 'image/jpeg', '.webp': 'image/webp', '.woff2': 'font/woff2', '.svg': 'image/svg+xml' };
export const stats = { php: 0, saves: 0, requests: [] };
function cgi(url, method, headers, body) {
  return new Promise((resolve) => {
    const env = { PATH: process.env.PATH, HOME: process.env.HOME, REDIRECT_STATUS: '1', REQUEST_METHOD: method, SCRIPT_FILENAME: `${ROOT}/index.php`, SCRIPT_NAME: '/index.php', REQUEST_URI: url.pathname + url.search, QUERY_STRING: url.search.slice(1), HTTP_HOST: 'guesstimate.local', SERVER_NAME: 'guesstimate.local', SERVER_PORT: '80', HTTP_ACCEPT: headers.accept || '*/*', SERVER_PROTOCOL: 'HTTP/1.1' };
    if (process.env.GST_TEST_UPLOADS) env.GST_TEST_UPLOADS = process.env.GST_TEST_UPLOADS;
    if (headers['content-type']) env.CONTENT_TYPE = headers['content-type'];
    if (headers['x-gst-nonce']) env.HTTP_X_GST_NONCE = headers['x-gst-nonce'];
    if (headers['x-test-user']) env.HTTP_X_TEST_USER = headers['x-test-user'];
    if (body) env.CONTENT_LENGTH = String(body.length);
    const php = spawn('php-cgi', ['-d', 'memory_limit=512M', '-d', 'display_errors=0', '-d', `auto_prepend_file=${PREPEND}`], { env });
    const chunks = [];
    php.stdout.on('data', (c) => chunks.push(c));
    php.stderr.on('data', () => {});
    php.on('close', () => {
      const raw = Buffer.concat(chunks);
      const split = raw.indexOf('\r\n\r\n');
      const head = raw.subarray(0, split).toString();
      const out = {}; let status = 200;
      head.split('\r\n').forEach((line) => {
        const i = line.indexOf(':'); if (i < 0) return;
        const k = line.slice(0, i).toLowerCase(); const v = line.slice(i + 1).trim();
        if (k === 'status') status = parseInt(v, 10); else if (k !== 'set-cookie') out[k] = v;
      });
      resolve({ status, headers: out, body: raw.subarray(split + 4) });
    });
    php.stdin.end(body || undefined);
  });
}
export async function attach(context) {
  await context.route('**/*', async (route) => {
    const req = route.request();
    const url = new URL(req.url());
    if (url.hostname !== 'guesstimate.local') return route.abort();
    const ext = path.extname(url.pathname);
    if (url.pathname.startsWith('/gst-test-uploads/')) {
      const name = path.normalize(decodeURIComponent(url.pathname)).replace('/gst-test-uploads/', '');
      // Starsze grafiki leżą w prawdziwych uploads: tylko odczyt.
      const body = await readFile(path.join(process.env.GST_TEST_UPLOADS, name)).catch(() => readFile(path.join(ROOT, 'wp-content/uploads', name)).catch(() => null));
      return body ? route.fulfill({ status: 200, contentType: MIME[ext] || 'application/octet-stream', body }) : route.fulfill({ status: 404, body: '' });
    }
    if (ext && ext !== '.php') {
      try {
        return route.fulfill({ status: 200, contentType: MIME[ext] || 'application/octet-stream', body: await readFile(ROOT + decodeURI(url.pathname)) });
      } catch { return route.fulfill({ status: 404, body: '' }); }
    }
    stats.php += 1;
    const body = req.postDataBuffer();
    stats.requests.push(`${req.method()} ${url.pathname}`);
    if (req.method() === 'POST' && /\/dokument$/.test(url.pathname)) stats.saves += 1;
    const res = await cgi(url, req.method(), req.headers(), body);
    // Playwright nie przechwytuje żądań po przekierowaniu, więc 302 wyszłoby poza mostek, do prawdziwej sieci.
    if (req.isNavigationRequest() && res.status >= 300 && res.status < 400 && res.headers.location) {
      return route.fulfill({ status: 200, contentType: 'text/html', body: `<script>location.replace(${JSON.stringify(res.headers.location)})</script>` });
    }
    return route.fulfill(res);
  });
}
