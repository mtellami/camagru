import assert from 'node:assert/strict';

const base = process.env.TEST_URL || 'http://localhost:8080';
const health = await fetch(`${base}/health`);
assert.equal(health.status, 200);
assert.equal(health.headers.get('server'), 'nginx');
assert.equal((await health.json()).ok, true);

for (const [path, type] of [
  ['/assets/css/style.css', 'text/css'],
  ['/assets/js/app.js', 'javascript'],
  ['/assets/images/overlays/orbit.png', 'image/png']
]) {
  const response = await fetch(`${base}${path}`);
  assert.equal(response.status, 200, path);
  assert.ok(response.headers.get('content-type').includes(type), path);
  assert.equal(response.headers.get('x-content-type-options'), 'nosniff', path);
}

for (const path of ['/.env', '/.git/config', '/src/bootstrap.php', '/tools/scripts/setup.php', '/storage/logs/php-error.log', '/other.php', '/index.php/other', '/assets/missing.css']) {
  assert.equal((await fetch(`${base}${path}`)).status, 404, path);
}

const gallery = await fetch(`${base}/gallery?page=1`, { headers: { Accept: 'application/json' } });
assert.equal(gallery.status, 200);
assert.equal((await gallery.json()).ok, true);
const oversized = await fetch(`${base}/photos`, { method: 'POST', body: Buffer.alloc(24 * 1024 * 1024 + 1) });
assert.equal(oversized.status, 413);
console.log('NGINX health, static assets, front-controller routing, private paths, and upload limit passed.');
