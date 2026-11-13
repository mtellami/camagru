# Camagru

A photo-sharing app built with PHP 8.4, PostgreSQL, and plain JavaScript. Capture or upload photos, add frames, create GIFs, and share likes and comments.

## Run

Requires Docker Compose, Make, and PHP CLI.

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

Nginx and PHP-FPM share the `web` container. PostgreSQL initializes new database volumes from `src/Database/schema.sql`. Data persists in Docker volumes.

PHP lives in `src/`, templates in `src/views/`, and static assets in `public/assets/`. JavaScript uses native modules; there is no package manager or build step.
