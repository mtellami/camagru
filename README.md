# Camagru

A small photo studio and public community gallery. Native PHP, PostgreSQL, HTML, CSS, and browser JavaScript; no application framework or runtime package dependencies.

## Project structure

```text
src/                    PHP application and page templates
  Database/             Database access and SQL migrations
  Http/                 Requests, responses, and HTTP errors
  Mail/                 Encrypted email outbox
  Media/                Image compositing and GIF assembly
  Security/             Authentication, CSRF, and rate limits
  views/                Page templates and partials
public/                 Web entry point, CSS, JavaScript, and images
tests/                  PHP checks, browser scenarios, and test tooling
  .artifacts/           Generated reports and screenshots (ignored)
tools/
  docker/               Dockerfile and Apache, PHP, and mail configuration
  scripts/              Setup, migrations, overlay generation, and worker
```

The root contains the README, Compose and Make entry points, dependency manifests, and local configuration templates. Runtime data is kept in Docker volumes.

## Run locally

Requirements: Docker Engine with Compose v2, Make, and PHP CLI for generating local configuration.

```sh
make up
```

Open **http://localhost:8080** and the email inbox at **http://localhost:8025**. Register, open the confirmation email in Mailpit, confirm the address, then sign in. All local emails are captured by Mailpit; nothing is sent to an external inbox.

If a port is occupied, edit the ignored `.env` generated on first run. Change `APP_PORT` and `APP_URL` together; change `MAILPIT_PORT` for the inbox. Then run `make up` again. Camera capture works on localhost; other HTTP hosts may be denied camera access by the browser. Upload mode works without a webcam.

`make up` generates random database credentials and the outbox encryption key only when `.env` is absent. Keep that file: changing its database password does not change credentials in an existing database volume, and changing `APP_KEY` prevents decryption of queued mail.

```sh
make down          # stop without deleting data
make logs          # container diagnostics
make test          # native PHP tests with isolated, cleaned-up fixtures
make lint          # PHP and browser JavaScript syntax
make reset         # asks for DELETE before removing all local data
```

Source is copied into the image. Run `make up` after changing application code. PostgreSQL, sessions, and generated images persist across normal restarts. Database ports are not published; the app and Mailpit bind to loopback only.

The web root is `public/`; uploaded and generated media stay in a private volume and are served through ready-state checks. Six transparent overlays are generated from our own PHP code during image construction. SQL migrations run once, under a database lock, before the web service starts.

## Features

- Email-confirmed signup; username/password login; reset emails; one-click logout.
- Username, verified email, password, and comment-notification settings.
- Public gallery with ten items per page, likes, and plain-text comments.
- Authenticated camera/upload studio; server-side transparent-overlay composition.
- Personal creation history and owner-only deletion.
- AJAX interactions, live overlay preview, infinite scrolling, sharing, and server-generated animated GIFs.

For a GIF, choose an overlay and record an eight-frame camera burst, or upload 2–8 JPEG/PNG frames in playback order. Inputs are bounded to 5 MiB per image, 12 megapixels per image, and 20 MiB per animation. Static output is 1280×960; GIF output is 480×360 with 250 ms per frame. All images are center-cropped to 4:3, and JPEG EXIF orientation is normalized.

Sharing uses the device share sheet where available and a copyable permalink otherwise. A social platform cannot retrieve a localhost URL; public crawler previews require a future public instance. Deployment configuration is intentionally absent.

## Email worker

The worker polls the encrypted database outbox. Successful registration, account changes, and comments commit their email jobs transactionally. Comment notifications default to enabled, include self-comments, and recheck the recipient's current preference before sending.

Transient failures retry up to five times with increasing delays. Delivery is at least once: an interruption after SMTP acceptance can produce a duplicate message. Expired account links are discarded. Request a fresh link from `/resend` or `/forgot`.

```sh
docker compose exec worker php tools/scripts/worker.php --once
docker compose exec worker php tools/scripts/worker.php --retry-failed
```

The same worker removes abandoned staged media, deleted images, expired rate-limit records, and old account tokens. Deletion hides a creation immediately; physical removal follows during maintenance. Error diagnostics are private files under `storage/logs/`, accessible with `docker compose exec web sh`.

SMTP is intentionally a local Mailpit relay, configured by `tools/docker/mail/msmtprc`; this repository does not configure an external email provider. `MAIL_FROM` controls the application sender. Mailpit host and port are infrastructure settings in that file.

## Verification

Browser checks require Node.js 22 or newer and pnpm (CI uses Node.js 22 and pnpm 10.30.3). These tools are development-only; the application does not load scripts from `node_modules` or a CDN.

```sh
pnpm install --frozen-lockfile
pnpm lint
pnpm exec playwright install --with-deps chromium firefox
pnpm test
```

For custom ports:

```sh
TEST_URL=http://localhost:8088 MAILPIT_URL=http://localhost:8028 pnpm test
```

Browser tests create identifiable test accounts and creations in the running instance. Use an isolated Compose project/database if you want to preserve a pristine gallery. Repeated runs also consume the registration and login rate limits; use fresh test data or wait for the limits to expire before rerunning. The pinned Chromium and Firefox engines cover account confirmation through the real Mailpit inbox, camera capture, upload, GIF creation, likes, comments, owner authorization, notifications, settings, deletion, and password reset. Traces are disabled to avoid retaining bearer links.

For independent GIF verification, install Pillow in a development virtual environment:

```sh
make test
docker compose cp web:/tmp/camagru-test.gif /tmp/camagru-test.gif
python3 -m venv /tmp/camagru-tools
/tmp/camagru-tools/bin/pip install Pillow==12.1.0
/tmp/camagru-tools/bin/python tests/tools/decode-gif.py /tmp/camagru-test.gif
```

GitHub Actions runs syntax checks, secret scanning, PHP tests, independent GIF decoding, Chromium/Firefox tests, and container privacy checks. Restart checks verify that users and image files persist and that the email worker reconnects after a database restart. Only failure screenshots are uploaded. CI uses no deployment secrets and publishes nothing.

## Evaluation checklist

- Run from a clean checkout using `make up`; restart without losing users or images.
- Complete signup via the local email inbox; test expired/reused confirmation and reset links.
- Confirm visitors can browse and cannot access the editor or mutate private data.
- Verify settings, one-click logout, and session revocation after password reset/change.
- Create photos through both camera and upload; capture must require an overlay.
- Try damaged files, SVG, fake MIME types, oversized dimensions, forged overlay IDs, and foreign photo deletion.
- Verify SQL/HTML payloads remain data, and missing/foreign CSRF tokens are rejected.
- Browse more than ten images using both page links and infinite scrolling.
- Verify notifications with the preference on/off, and pause/restart the worker to check durability.
- Check GIF frames with an independent decoder and verify AJAX failure/retry behavior.
- Test 375 px, tablet, and desktop layouts; keyboard focus, labels, and errors.
- Manually verify Firefox 41 and Chrome 46 in an isolated local environment. ES5 syntax and API fallbacks support these targets; current-engine CI does not certify the old engines.
- Inspect browser console and private error logs after normal flows. Apache/worker lifecycle output should remain quiet.
