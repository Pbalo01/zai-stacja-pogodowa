# Domowa stacja pogodowa – ZAI 26Z

Aplikacja zbiera pomiary z czujników (temperatura, wilgotność, PM2.5) i udostępnia je przez REST API
zgodne z kontraktem `zai-api-26z.yaml`.

**Technologie:** PHP 8.3, Symfony 7.4, PostgreSQL 16, Docker (FrankenPHP), generator w Node.js.

## Uruchomienie lokalne

Wymagane: Docker, Node.js 20+.

```bash
cp .env.example .env        # ustaw ADMIN_PASSWORD i APP_SECRET
```

Środowisko dev montuje katalog `backend/` do kontenera, więc zmiany w PHP i `templates/` widać bez przebudowy:

```bash
docker compose --env-file .env -f docker/dev/compose.yaml up -d --build
```

Środowisko prod ma kod w obrazie, tak jak na Render:

```bash
docker compose --env-file .env -f docker/prod/compose.yaml up -d --build
```

Aplikacja: http://localhost:8080, baza: localhost:5433. Oba środowiska używają tej samej bazy,
więc uruchamia się jedno naraz. Przy starcie wykonują się migracje i tworzy się konto administratora z `.env`.

## Dane przykładowe

```bash
node generator/seed-demo.mjs --api http://localhost:8080 --user admin --pass '<hasło>'
```

Tworzy 3 serie (temperatura, wilgotność, PM2.5) i wysyła do nich wyniki generatorem przez API.

## Generator danych

Wysyła wyniki przez `POST /api/measurements` z kluczem czujnika.

```bash
# wyniki z przeszłości
node generator/sensor.mjs --api http://localhost:8080 --key <klucz> --mode history --count 48 --shape sine --min 15 --max 25

# bieżące wyniki co 10 s
node generator/sensor.mjs --api http://localhost:8080 --key <klucz> --mode live --interval 10 --min 30 --max 60
```

| Parametr | Opis |
| --- | --- |
| `--api` | adres aplikacji |
| `--key` | klucz API czujnika (lub zmienna `SENSOR_API_KEY`) |
| `--mode` | `history` – wyniki z przeszłości, `live` – bieżące |
| `--count` | liczba wyników |
| `--interval` | odstęp między wynikami w sekundach |
| `--shape` | `random`, `sine` lub `range` |
| `--min`, `--max` | zakres wartości |
| `--source openmeteo` | rzeczywiste dane z Open-Meteo (`--lat`, `--lon`, `--param pm2_5`) |

## Wdrożenie

Render (Docker, `backend/Dockerfile`) + baza PostgreSQL w Neon.
Darmowy plan Render usypia aplikację – pierwsze uruchomienie może trwać ok. 1 minuty.