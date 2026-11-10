#!/usr/bin/env node
// Emulator czujnika „Domowej stacji pogodowej” (F13).
// Wysyła wyniki do API aplikacji: POST /api/measurements z nagłówkiem X-API-Key.
// Node.js 20+ (wbudowany fetch), bez zależności. Pomoc: node sensor.mjs --help

import { parseArgs } from 'node:util';

const HELP = `
Użycie: node generator/sensor.mjs --api <URL> --key <KLUCZ> [opcje]

Połączenie:
  --api URL          adres aplikacji, np. http://localhost:8080 (wymagany)
  --key KLUCZ        klucz API czujnika (lub zmienna środowiskowa SENSOR_API_KEY)

Tryb pracy:
  --mode history     wysyła --count wyników ze znacznikami czasu z przeszłości (domyślnie)
  --mode live        wysyła bieżący wynik co --interval sekund (do Ctrl+C lub --count wyników)
  --count N          liczba wyników (history: domyślnie 24; live: bez limitu)
  --interval SEK     odstęp między wynikami w sekundach (history: 3600, live: 60)
  --start ISO        (history) czas pierwszego wyniku, np. 2026-11-01T00:00:00Z;
                     domyślnie ostatni wynik przypada na ostatnią pełną wielokrotność
                     --interval (np. pełną godzinę), więc serie mają wspólne znaczniki czasu

Sposób generowania wartości:
  --shape random     błądzenie losowe w przedziale [min, max] (domyślnie)
  --shape sine       przebieg sinusoidalny z niewielkim szumem (okres: --period wyników, domyślnie 24)
  --shape range      wartości losowe o rozkładzie jednostajnym w [min, max]
  --min, --max       przedział generowanych wartości (domyślnie 0 i 100)

Rzeczywiste dane (Open-Meteo, bez klucza):
  --source openmeteo --lat 52.23 --lon 21.01 --param pm2_5
                     param: pm2_5 | pm10 | temperature_2m | relative_humidity_2m
                     history: ostatnie --count pomiarów godzinowych, live: bieżący odczyt

Inne:
  --dry-run          tylko wypisuje wyniki, nic nie wysyła
  --help             ta pomoc

Przykłady:
  SENSOR_API_KEY=... node generator/sensor.mjs --api http://localhost:8080 --count 48 --shape sine --min 15 --max 25
  node generator/sensor.mjs --api https://app.example.com --key ... --mode live --interval 10 --shape random --min 30 --max 60
  node generator/sensor.mjs --api http://localhost:8080 --key ... --source openmeteo --lat 52.23 --lon 21.01 --param pm2_5 --count 48
`;

const AIR_QUALITY_PARAMS = new Set(['pm2_5', 'pm10']);
const WEATHER_PARAMS = new Set(['temperature_2m', 'relative_humidity_2m']);

function fail(message) {
  console.error(`Błąd: ${message}\nUżyj --help, aby zobaczyć dostępne opcje.`);
  process.exit(2);
}

function number(value, name, { integer = false, positive = false } = {}) {
  if (value === undefined) return undefined;
  const n = Number(value);
  if (!Number.isFinite(n) || (integer && !Number.isInteger(n)) || (positive && n <= 0)) {
    fail(`parametr --${name} musi być ${integer ? 'liczbą całkowitą' : 'liczbą'}${positive ? ' dodatnią' : ''} (podano „${value}”)`);
  }
  return n;
}

function readOptions() {
  let values;
  try {
    ({ values } = parseArgs({
      options: {
        api: { type: 'string' }, key: { type: 'string' },
        mode: { type: 'string', default: 'history' },
        count: { type: 'string' }, interval: { type: 'string' }, start: { type: 'string' },
        shape: { type: 'string', default: 'random' }, min: { type: 'string', default: '0' }, max: { type: 'string', default: '100' },
        period: { type: 'string', default: '24' },
        source: { type: 'string' }, lat: { type: 'string' }, lon: { type: 'string' }, param: { type: 'string' },
        'dry-run': { type: 'boolean', default: false }, help: { type: 'boolean', default: false },
      },
    }));
  } catch (e) {
    fail(e.message);
  }
  if (values.help) {
    console.log(HELP);
    process.exit(0);
  }

  const o = {
    api: values.api?.replace(/\/+$/, ''),
    key: values.key ?? process.env.SENSOR_API_KEY,
    mode: values.mode,
    shape: values.shape,
    min: number(values.min, 'min'),
    max: number(values.max, 'max'),
    period: number(values.period, 'period', { positive: true }),
    count: number(values.count, 'count', { integer: true, positive: true }),
    interval: number(values.interval, 'interval', { positive: true }),
    source: values.source,
    lat: number(values.lat, 'lat'),
    lon: number(values.lon, 'lon'),
    param: values.param,
    dryRun: values['dry-run'],
  };

  if (!o.api && !o.dryRun) fail('podaj adres aplikacji --api');
  if (!o.key && !o.dryRun) fail('podaj klucz czujnika --key albo ustaw zmienną SENSOR_API_KEY');
  if (!['history', 'live'].includes(o.mode)) fail('--mode przyjmuje wartości history lub live');
  if (!['random', 'sine', 'range'].includes(o.shape)) fail('--shape przyjmuje wartości random, sine lub range');
  if (o.min >= o.max) fail('--min musi być mniejsze niż --max');

  if (o.source !== undefined) {
    if (o.source !== 'openmeteo') fail('jedyne obsługiwane źródło to --source openmeteo');
    if (o.lat === undefined || o.lon === undefined) fail('dla --source openmeteo podaj --lat i --lon');
    if (!AIR_QUALITY_PARAMS.has(o.param) && !WEATHER_PARAMS.has(o.param)) {
      fail('--param przyjmuje wartości pm2_5, pm10, temperature_2m lub relative_humidity_2m');
    }
  }

  o.interval ??= o.mode === 'history' ? 3600 : 60;
  if (o.mode === 'history') o.count ??= 24;

  if (values.start !== undefined) {
    if (o.mode !== 'history') fail('--start ma sens tylko w trybie history');
    o.start = Date.parse(values.start);
    if (Number.isNaN(o.start)) fail(`--start nie jest poprawną datą ISO 8601 („${values.start}”)`);
  }
  return o;
}

const round = (v) => Math.round(v * 100) / 100;

function makeGenerator({ shape, min, max, period }) {
  const span = max - min;
  let current = min + span / 2;
  let i = 0;
  return () => {
    let value;
    if (shape === 'range') {
      value = min + Math.random() * span;
    } else if (shape === 'sine') {
      const noise = (Math.random() - 0.5) * span * 0.05;
      value = min + span / 2 + (span * 0.4) * Math.sin((2 * Math.PI * i) / period) + noise;
    } else {
      current += (Math.random() - 0.5) * span * 0.1;
      value = current = Math.min(max, Math.max(min, current));
    }
    i++;
    return round(Math.min(max, Math.max(min, value)));
  };
}

function openMeteoUrl({ param, lat, lon }, query) {
  const base = AIR_QUALITY_PARAMS.has(param)
    ? 'https://air-quality-api.open-meteo.com/v1/air-quality'
    : 'https://api.open-meteo.com/v1/forecast';
  const qs = new URLSearchParams({ latitude: String(lat), longitude: String(lon), timezone: 'UTC', ...query });
  return `${base}?${qs}`;
}

async function fetchJson(url) {
  const res = await fetch(url, { signal: AbortSignal.timeout(20_000) });
  if (!res.ok) throw new Error(`Open-Meteo odpowiedziało ${res.status} ${res.statusText}`);
  return res.json();
}

// forecast_days=1 dokłada prognozę – odcinamy godziny z przyszłości.
async function openMeteoHistory(o) {
  const pastDays = Math.min(92, Math.ceil(o.count / 24) + 1);
  const data = await fetchJson(openMeteoUrl(o, { hourly: o.param, past_days: String(pastDays), forecast_days: '1' }));
  const now = Date.now();
  const points = data.hourly.time
    .map((t, i) => ({ timestamp: `${t}:00Z`, value: data.hourly[o.param][i] }))
    .filter((p) => p.value !== null && Date.parse(p.timestamp) <= now);
  return points.slice(-o.count);
}

async function openMeteoCurrent(o) {
  const data = await fetchJson(openMeteoUrl(o, { current: o.param }));
  const value = data.current?.[o.param];
  if (value === null || value === undefined) throw new Error('Open-Meteo nie zwróciło bieżącej wartości');
  return value;
}

async function send(o, body) {
  if (o.dryRun) {
    console.log(`  (próba) ${JSON.stringify(body)}`);
    return { ok: true };
  }
  let res;
  try {
    res = await fetch(`${o.api}/api/measurements`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-API-Key': o.key },
      body: JSON.stringify(body),
      signal: AbortSignal.timeout(30_000),
    });
  } catch (e) {
    console.error(`✘ Brak połączenia z ${o.api}: ${e.cause?.message ?? e.message}`);
    return { ok: false, network: true };
  }

  const text = await res.text();
  let json;
  try { json = text ? JSON.parse(text) : undefined; } catch { json = undefined; }

  if (res.status === 201) {
    console.log(`✔ 201 zapisano wynik #${json?.id}: ${json?.value} @ ${json?.timestamp}`);
    return { ok: true };
  }

  const title = json?.title ?? res.statusText;
  const detail = json?.detail ? ` – ${json.detail}` : '';
  const fields = (json?.errors ?? []).map((e) => `\n    • ${e.field}: ${e.message}`).join('');
  console.error(`✘ ${res.status} ${title}${detail}${fields}\n    wysłano: ${JSON.stringify(body)}`);
  return { ok: false, status: res.status };
}

const isFatal = (r) => r.status === 401 || r.status === 403 || r.status === 404 || r.status === 415;

async function runHistory(o) {
  let points;
  if (o.source) {
    points = await openMeteoHistory(o);
    console.log(`Open-Meteo: pobrano ${points.length} pomiarów ${o.param} dla (${o.lat}, ${o.lon}).`);
  } else {
    const next = makeGenerator(o);
    const stepMs = o.interval * 1000;
    const lastAligned = Math.floor(Date.now() / stepMs) * stepMs;
    const start = o.start ?? lastAligned - (o.count - 1) * stepMs;
    points = Array.from({ length: o.count }, (_, i) => ({
      timestamp: new Date(start + i * stepMs).toISOString(), value: next(),
    }));
  }

  let sent = 0;
  let rejected = 0;
  for (const p of points) {
    const r = await send(o, { value: p.value, timestamp: p.timestamp });
    if (r.ok) sent++;
    else if (r.network || isFatal(r)) {
      console.error('Przerywam – kolejne wyniki też zostałyby odrzucone.');
      process.exitCode = 1;
      break;
    } else rejected++;
  }
  console.log(`\nWysłano ${sent}, odrzucono ${rejected} z ${points.length} wyników.`);
  if (rejected) process.exitCode = 1;
}

async function runLive(o) {
  const next = makeGenerator(o);
  let sent = 0;
  let rejected = 0;
  let stopping = false;
  process.on('SIGINT', () => {
    stopping = true;
    console.log(`\nZatrzymano. Wysłano ${sent}, odrzucono ${rejected} wyników.`);
    process.exit(0);
  });

  console.log(`Tryb live: wynik co ${o.interval} s${o.count ? `, łącznie ${o.count}` : ''} (Ctrl+C kończy).`);
  for (let i = 0; !stopping && (!o.count || i < o.count); i++) {
    if (i > 0) await new Promise((resolve) => setTimeout(resolve, o.interval * 1000));
    let value;
    try {
      value = o.source ? await openMeteoCurrent(o) : next();
    } catch (e) {
      console.error(`✘ ${e.message} – spróbuję ponownie za ${o.interval} s`);
      continue;
    }
    // Bez pola timestamp – serwer przypisze bieżący czas
    const r = await send(o, { value });
    if (r.ok) sent++;
    else if (isFatal(r)) {
      console.error('Przerywam – klucz czujnika jest nieprawidłowy lub czujnik został wyrejestrowany.');
      process.exitCode = 1;
      return;
    } else if (!r.network) rejected++; // pojedyncze 422 nie przerywa pracy
  }
  console.log(`\nWysłano ${sent}, odrzucono ${rejected} wyników.`);
}

const options = readOptions();
try {
  await (options.mode === 'history' ? runHistory(options) : runLive(options));
} catch (e) {
  console.error(`✘ ${e.message}`);
  process.exitCode = 1;
}
