# Camagru

A photo-sharing app built with PHP 8.4, PostgreSQL, and plain JavaScript. Capture or upload photos, add frames, create GIFs, and share likes and comments.

See [ARCHITECTURE.md](ARCHITECTURE.md) for Mermaid diagrams tracing the UI through the code, database, media storage, and email delivery.

## Run

Requires Docker Compose and Make. Prepare `.env` before starting:

```sh
cp .env.example .env
chmod 600 .env
```

Replace both `GENERATE_ME` values in `.env`: use a separate random value for `DB_PASSWORD` and `APP_KEY`. Generate each with `openssl rand -hex 32` (run it twice). `APP_KEY` must contain exactly 64 lowercase hexadecimal characters. Review the other values for your environment.

Keep `APP_URL` equal to the browser URL, including its port, so CSRF origin checks work. Set `COOKIE_SECURE=1` when using HTTPS.

The app requires an existing `.env`; startup rejects missing configuration, placeholder secrets, and invalid key/URL/port settings. Configuration is never generated automatically.

Then start the app:

```sh
make up
```

- App: http://localhost:8080
- Email inbox: http://localhost:8025

Open the confirmation email in Mailpit after signing up. Change `APP_URL`, `APP_PORT`, and `MAILPIT_PORT` in `.env` if needed.

```sh
make down   # Stop; keep accounts and images
make logs   # Follow service logs
```

Nginx and PHP-FPM share the `web` container. Container configuration lives in `docker/`. PostgreSQL initializes new database volumes from `src/Database/schema.sql`. Data persists in Docker volumes.

PHP lives in `src/`, templates in `src/Views/`, and static assets in `public/assets/`. JavaScript uses native modules; there is no package manager or build step.

Select several stickers to combine them on photos or GIFs; select None to clear them. The live preview and server apply stickers in picker order.
