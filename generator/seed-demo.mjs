#!/usr/bin/env node
// Dane przykładowe (F11): zakłada przez API trzy serie stacji pogodowej,
// rejestruje dla każdej czujnik i uruchamia generator sensor.mjs w trybie history.
// Wyniki trafiają do aplikacji wyłącznie przez POST /api/measurements.
//
//   node generator/seed-demo.mjs --api http://localhost:8080 --user admin --pass '...'
//   (hasło można też podać w zmiennej ADMIN_PASSWORD)

import { parseArgs } from 'node:util';
import { spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';

const SERIES = [
  {
    series: { name: 'Temperatura – salon', minValue: -10, maxValue: 40, color: '#d62728', icon: 'circle', unit: '°C' },
    generator: ['--shape', 'sine', '--min', '19', '--max', '24'],
  },
  {
    series: { name: 'Wilgotność – salon', minValue: 0, maxValue: 100, color: '#1f77b4', icon: 'square', unit: '%' },
    generator: ['--shape', 'random', '--min', '35', '--max', '60'],
  },
  {
    series: { name: 'PM2.5 – Warszawa', minValue: 0, maxValue: 500, color: '#2ca02c', icon: 'triangle', unit: 'µg/m³' },
    generator: ['--source', 'openmeteo', '--lat', '52.23', '--lon', '21.01', '--param', 'pm2_5'],
    fallback: ['--shape', 'random', '--min', '5', '--max', '60'],
  },
];

const { values: o } = parseArgs({
  options: {
    api: { type: 'string' }, user: { type: 'string' }, pass: { type: 'string' },
    count: { type: 'string', default: '48' }, force: { type: 'boolean', default: false },
  },
});
o.user ??= process.env.ADMIN_USERNAME ?? 'admin';
o.pass ??= process.env.ADMIN_PASSWORD;
if (!o.api || !o.pass) {
  console.error('Użycie: node generator/seed-demo.mjs --api <URL> --user <login> --pass <hasło> [--count 48] [--force]');
  process.exit(2);
}
const api = o.api.replace(/\/+$/, '');
const sensorScript = fileURLToPath(new URL('./sensor.mjs', import.meta.url));

async function call(method, path, { token, body } = {}) {
  const headers = { Accept: 'application/json' };
  if (token) headers.Authorization = `Bearer ${token}`;
  if (body) headers['Content-Type'] = 'application/json';
  const res = await fetch(api + path, { method, headers, body: body && JSON.stringify(body) });
  const json = await res.json().catch(() => undefined);
  if (!res.ok) throw new Error(`${method} ${path}: ${res.status} ${json?.title ?? ''} ${json?.detail ?? ''}`);
  return json;
}

function generate(key, args) {
  const r = spawnSync(process.execPath, [sensorScript, '--api', api, '--mode', 'history', '--count', o.count, ...args], {
    stdio: 'inherit', env: { ...process.env, SENSOR_API_KEY: key },
  });
  return r.status === 0;
}

const { accessToken: token } = await call('POST', '/api/auth/login', { body: { username: o.user, password: o.pass } });
const existing = await call('GET', '/api/series');

for (const def of SERIES) {
  let series = existing.find((s) => s.name === def.series.name);
  if (!series) {
    series = await call('POST', '/api/series', { token, body: def.series });
    console.log(`\n＋ Utworzono serię „${series.name}” (#${series.id})`);
  } else {
    const measurements = await call('GET', `/api/measurements?series=${series.id}&limit=10000`);
    if (measurements.length >= 15 && !o.force) {
      console.log(`\n= Seria „${series.name}” ma już ${measurements.length} wyników – pomijam (--force wymusza)`);
      continue;
    }
    console.log(`\n= Seria „${series.name}” (#${series.id}) już istnieje`);
  }

  const date = new Date().toISOString().slice(0, 10);
  const sensor = await call('POST', '/api/sensors', { token, body: { name: `Generator ${date} – ${series.name}`, seriesId: series.id } });
  console.log(`＋ Zarejestrowano czujnik #${sensor.id}; uruchamiam generator…`);

  if (!generate(sensor.apiKey, def.generator) && def.fallback) {
    console.log('Źródło rzeczywiste niedostępne – generuję dane syntetyczne.');
    generate(sensor.apiKey, def.fallback);
  }
}

await call('POST', '/api/auth/logout', { token });
console.log('\nGotowe.');
