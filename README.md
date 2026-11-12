# Camagru

Camagru is a photo-sharing web app for turning everyday moments into playful creations. Capture a photo with your webcam or upload an image, add a transparent overlay, and share it in the community gallery. You can also create animated GIFs, like and comment on photos, and manage your own creations.

Accounts include email confirmation, password recovery, profile settings, and optional comment notifications. The home page introduces the app, the gallery shows community photos, and the studio is where signed-in users create their images.

## Built with

- **PHP 8.4** — application logic, authentication, image processing, and email delivery.
- **PostgreSQL** — accounts, photos, likes, and comments.
- **NGINX + PHP-FPM** — serves the website and runs PHP.
- **HTML, CSS, and JavaScript** — the interface, webcam capture, and interactive features.
- **Mailpit** — a local inbox for confirmation, reset, and notification emails.
- **Docker Compose** — runs the services together.

## Run locally

Install Docker with Compose v2, Make, and PHP CLI, then run this from the project directory:

```sh
make up
```

This creates your local `.env`, builds the images, sets up the database, and starts the app.

- App: [localhost:8080](http://localhost:8080)
- Email inbox: [localhost:8025](http://localhost:8025)

Create an account, open its confirmation email in Mailpit, and click the confirmation button before signing in. Emails stay in the local inbox.

If either port is already in use, edit `.env`. For example:

```dotenv
APP_URL=http://localhost:8088
APP_PORT=8088
MAILPIT_PORT=8028
```

Run `make up` again, then use the updated ports. Webcam capture works on localhost; you can also upload JPEG or PNG images.

## Everyday commands

```sh
make up      # Start the app and rebuild after code changes
make down    # Stop the app, keeping your data
make logs    # View container logs
make reset   # Delete local data after typing DELETE to confirm
```

Accounts and images are stored in Docker volumes and survive normal restarts. Keep your generated `.env` file, which contains the database credentials and application key.

## PHP formatting

PHP-CS-Fixer is a development tool and does not add a runtime dependency to the app. With Composer installed, run:

```sh
composer install
make format       # Apply the configured PHP style
make format-check # Check formatting without changing files
```
