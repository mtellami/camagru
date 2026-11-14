# Camagru architecture

This guide follows a user action from the browser UI through JavaScript, PHP, PostgreSQL, and media storage. Every linked path points to the implementation in this repository.

## 1. The whole app

Camagru uses PHP 8.4, PostgreSQL, and native JavaScript modules. PHP renders the initial page. JavaScript adds camera capture, previews, gallery loading, and form updates. There is no frontend build step.

```mermaid
flowchart TD
    Browser[Browser] --> Nginx[Nginx]
    Nginx -->|Static files| Assets[public/assets]
    Nginx -->|PHP-FPM| Entry[public/index.php]
    Entry --> App[src/App.php: request checks and routing]
    App --> Controllers[Http/Controllers: feature actions]
    Controllers --> Auth[Security/Auth.php]
    Controllers --> Http[Http/Http.php: responses]
    Http --> Views[src/Views: templates and layout]
    Views -->|HTML| Browser
    Controllers --> Repository[Repositories/PhotoRepository.php: shared photo reads]
    Repository --> DB[Database/Database.php]
    Controllers --> DB
    Auth --> DB
    DB --> PG[(PostgreSQL)]
    Controllers --> Publisher[Media/PhotoPublisher.php]
    Publisher --> Images[Media/Images.php: decode and composite]
    Publisher --> Gif[Media/Gif.php: assemble animation]
    Publisher --> DB
    Publisher --> Disk[(storage/media)]
    Entry -->|Request shutdown| Maintenance[Maintenance.php]
    Maintenance --> Publisher
    Maintenance --> Outbox[Mail/Outbox.php]
    Outbox --> DB
    Outbox -->|PHP mail and msmtp| Mailpit[Mailpit]
```

[`App.php`](src/App.php) dispatches URLs and applies shared checks. Feature behavior lives in [`Http/Controllers/`](src/Http/Controllers/). Controllers use [`PhotoRepository.php`](src/Repositories/PhotoRepository.php) for shared photo reads and [`Database.php`](src/Database/Database.php) for other prepared queries and transactions. There is no ORM.

## 2. How PHP gets JavaScript running in the browser

**PHP generates HTML. The browser reads that HTML, downloads JavaScript, and executes it.** PHP does not directly call JavaScript functions.

[`Http::render()`](src/Http/Http.php) renders a page template inside [`layout.php`](src/Views/layout.php). The layout includes this module entry point:

```html
<script type="module" src="/assets/js/app.js?v=24"></script>
```

The query parameter versions the entry URL. The browser requests this file from Nginx, then downloads its imported modules. Module scripts execute after the HTML has been parsed, so the initializers can find the page's elements.

[`app.js`](public/assets/js/app.js) imports and calls `initUi()`, `initAuth()`, `initGallery()`, `initForms()`, `initSettings()`, `initPhoto()`, and `initEditor()`. An initializer attaches event listeners to matching elements or uses delegated events on `document`. For example, `initEditor()` returns immediately unless it finds `#editor`.

```mermaid
sequenceDiagram
    actor User
    participant Browser
    participant Nginx
    participant PHP as PHP controllers and Http.render
    participant Layout as Views/layout.php
    participant JS as app.js and imported modules
    User->>Browser: Open a page
    Browser->>Nginx: GET page URL
    Nginx->>PHP: Execute public/index.php through PHP-FPM
    PHP->>Layout: Render page inside layout
    Layout-->>Browser: HTML including module script tag
    Browser->>Nginx: GET /assets/js/app.js and its imports
    Nginx-->>Browser: JavaScript files
    Browser->>JS: Execute module and call initializers
    JS->>Browser: Attach event listeners to rendered elements
    User->>Browser: Click a button or submit a form
    Browser->>JS: Run the registered listener
    opt Action needs server data
        JS->>PHP: New HTTP request through api.js
        PHP-->>JS: JSON response
        JS->>Browser: Update the visible page
    end
```

Changing a button's HTML starts in a PHP view. Changing what happens when it is clicked starts in the JavaScript module that registers its listener.

## 3. Which screen uses which code?

All URLs are dispatched in [`App.php`](src/App.php). `{id}` denotes a positive numeric photo ID. All POST requests pass the shared CSRF check before reaching a controller.

| Screen or action | Template / browser behavior | Responsible PHP code |
| --- | --- | --- |
| Home: `GET /` | [home.php](src/Views/home.php) | `App::run()` renders the page. |
| Gallery: `GET /gallery` | [gallery.php](src/Views/gallery.php), [partials/card.php](src/Views/partials/card.php), [gallery.js](public/assets/js/gallery.js) | [GalleryController::show()](src/Http/Controllers/GalleryController.php) and `PhotoRepository::gallery()`. |
| Editor: `GET /editor` | [editor.php](src/Views/editor.php), [editor.js](public/assets/js/editor.js), [camera.js](public/assets/js/camera.js) | [EditorController::show()](src/Http/Controllers/EditorController.php) requires login and loads personal history. |
| Personal history: `GET /editor/photos` | History grid and `gallery.js` | `GalleryController::show(true)` returns owner-filtered HTML or JSON. |
| Sticker catalog: `GET /overlays` | Editor reads the same catalog directly while rendering | `EditorController::overlays()` exposes `Images::OVERLAYS` as JSON. |
| Publish: `POST /photos` or `/photos/gif` | Editor forms and `editor.js` | `EditorController::publish()` checks login and limits, then calls `PhotoPublisher::publish()`. |
| Photo: `GET /photos/{id}` | [photo.php](src/Views/photo.php), [photo.js](public/assets/js/photo.js), [forms.js](public/assets/js/forms.js) | [PhotoController::show()](src/Http/Controllers/PhotoController.php) loads a ready photo and comments in pages of 20. |
| Like/unlike: `POST /photos/{id}/like` or `/unlike` | Card/photo form and `forms.js` | `PhotoController::interact()` saves liked state and returns its count. |
| Comment: `POST /photos/{id}/comments` | Photo form and `forms.js` | `PhotoController::interact()` inserts a comment and optionally queues email. |
| Delete comment: `POST /photos/{id}/comments/{commentId}/delete` | Photo template, dialog, and `forms.js` | `PhotoController::deleteComment()` permits only the comment's owner. |
| Delete creation: `POST /photos/{id}/delete` | Photo menu and `forms.js` | `PhotoController::interact()` checks ownership and marks the photo `deleting`. |
| Accounts: `/register`, `/login`, `/forgot`, `/resend`, `/verify`, `/reset` | [auth.php](src/Views/auth.php), [auth.js](public/assets/js/auth.js) | [AuthController](src/Http/Controllers/AuthController.php): GET → `show()`; POST → `register()`, `login()`, `requestEmail()`, `verify()`, or `reset()`. |
| Logout: `POST /logout` | Navigation form in `layout.php` | `AuthController::logout()`. |
| Settings: `GET /settings` | [settings.php](src/Views/settings.php), [settings.js](public/assets/js/settings.js), password visibility in `auth.js` | [SettingsController::show()](src/Http/Controllers/SettingsController.php). |
| Update settings: POST `/settings/username`, `/email`, `/password`, `/notifications` | Settings forms, notification AJAX handled by `forms.js` | `SettingsController::update()` applies the selected preference. Each URL includes the `/settings` prefix. |
| Media: `GET /media/{id}/image`, `/thumb`, `/poster` | Image URLs in templates/cards | `PhotoController::media()` streams files only for ready photos. |
| Health: `GET /health` | No UI | `App::run()` executes `SELECT 1` and returns JSON without maintenance. |
| Errors | [error.php](src/Views/error.php) or form feedback | [public/index.php](public/index.php) catches failures and formats HTML or JSON. |

### Shared browser code

| Module | Responsibility |
| --- | --- |
| [app.js](public/assets/js/app.js) | Import modules and call their initializers. |
| [api.js](public/assets/js/api.js) | XMLHttpRequest, JSON responses, CSRF header, and random submission keys. |
| [forms.js](public/assets/js/forms.js) | Handle forms marked `data-ajax`, including likes, comments, deletion, and notification preferences. |
| [photo.js](public/assets/js/photo.js) | Photo sharing popover and copying links/embed snippets. |
| [settings.js](public/assets/js/settings.js) | Settings sidebar selection. |
| [auth.js](public/assets/js/auth.js) | Password visibility and account password-rule feedback. |
| [ui.js](public/assets/js/ui.js) | Toasts, busy/status messages, menus, dialogs, and back navigation. |
| [dom.js](public/assets/js/dom.js), [icons.js](public/assets/js/icons.js) | Shared DOM/SVG construction and icon definitions. |
| [style.css](public/assets/css/style.css), [theme.css](public/assets/css/theme.css) | Shared layout and visual styling, loaded by `layout.php`. |

### HTML forms and JSON use the same endpoints

Authentication and most settings forms submit normally. `forms.js` intercepts `data-ajax` forms, while `editor.js` handles publishing. [`Http::success()`](src/Http/Http.php) responds according to `Accept`:

- `application/json`: `{ok: true, data: {message, redirect, ...}}`. JavaScript decides how to update the screen or follow a redirect.
- Normal form: store a flash message and issue a `303` redirect.
- Errors caught by `index.php`: JSON `{ok: false, error: {code, message, fields}}` or an HTML error page. Expected native login errors redirect to `/login` with a session-stored message.

## 4. Taking or uploading a photo / GIF

```mermaid
sequenceDiagram
    actor User
    participant UI as editor.php and editor.js
    participant Camera as camera.js
    participant API as api.js
    participant Controller as EditorController
    participant Publisher as PhotoPublisher
    participant Images as Images.php
    participant Gif as Gif.php
    participant DB as PostgreSQL
    participant Disk as storage/media
    User->>UI: Enable camera or select JPEG/PNG files
    opt Camera source
        UI->>Camera: captureFrame with target dimensions
        Camera-->>UI: Cropped JPEG Blob
    end
    User->>UI: Choose stickers and publish
    UI->>API: FormData with images, stickers, and submission key
    API->>Controller: POST /photos or /photos/gif with CSRF
    Controller->>Controller: Check login, rate limit, and overlays
    Controller->>Publisher: publish
    Publisher->>DB: Check submission key and insert staging row
    loop Each input image
        Publisher->>Images: upload and composite
        Images-->>Publisher: Validated and composed canvas
        opt First frame
            Publisher->>Disk: Save JPEG poster and thumbnail
        end
    end
    opt GIF mode
        Publisher->>Gif: assemble GD-encoded frames
        Gif-->>Publisher: Looping GIF bytes
        Publisher->>Disk: Save GIF
    end
    Publisher->>DB: Set photo state to ready
    Publisher-->>Controller: Photo row
    Controller-->>API: 201 JSON with photo and redirect
    API-->>UI: Success callback
    UI->>UI: Add history thumbnail, refresh submission key, show toast
```

[`editor.js`](public/assets/js/editor.js) owns camera permission, source switching, modes, sticker previews, uploaded frame previews, and eight-frame camera bursts. [`camera.js`](public/assets/js/camera.js) center-crops video to a canvas and encodes a JPEG Blob. Browser sticker layers provide a preview. PHP applies the actual layers to the saved output.

[`Images.php`](src/Media/Images.php) handles image operations:

- `overlays()` validates catalog entries and removes duplicates while preserving order.
- `upload()` checks PHP upload provenance. `decode()` checks MIME, size, dimensions, decoding, and JPEG EXIF orientation.
- `composite()` crops/resizes and applies sticker layers. Photos are **1280 × 960**; GIF frames are **480 × 360**.

[`PhotoPublisher.php`](src/Media/PhotoPublisher.php) coordinates persistence:

1. Validate the submission key and return an existing ready result for a retry. The database enforces uniqueness per user.
2. Insert a `staging` row and process the uploaded images with `Images`.
3. Save a first-frame JPEG poster and **320 × 240** thumbnail. For GIFs, [`Gif::assemble()`](src/Media/Gif.php) combines server-generated frames into a looping animation, normally 250 ms per frame.
4. Write files through temporary paths and rename them, then mark the row `ready`. A caught failure removes tracked outputs and the staging row.

Each input is limited to 5 MiB, 12 megapixels, and 6000 pixels per side. GIFs require 2–8 JPEG/PNG frames, at most 20 MiB combined. The complete POST is limited to 24 MiB. PHP enforces these limits independently of browser feedback.

### Media files and lifecycle

The database stores metadata and a random `media_key`. Encoded files live outside `public/`:

| File under `storage/media/` | URL |
| --- | --- |
| `{media_key}.jpg` | `/media/{id}/poster`, and `/media/{id}/image` for a photo |
| `{media_key}-thumb.jpg` | `/media/{id}/thumb` |
| `{media_key}.gif` | `/media/{id}/image` for a GIF |

`PhotoRepository::find()` and `gallery()` expose only ready rows. `PhotoController::media()` checks the row before streaming bytes.

```mermaid
stateDiagram-v2
    [*] --> staging: Publisher inserts metadata
    staging --> ready: Output files saved
    staging --> [*]: Publishing failure removes row and files
    staging --> [*]: Maintenance removes abandoned staging after 10 minutes
    ready --> deleting: Owner submits delete
    deleting --> [*]: Maintenance removes files and database row
```

## 5. Accounts, sessions, and confirmation

```mermaid
sequenceDiagram
    actor User
    participant UI as auth.php
    participant Controller as AuthController
    participant Auth as Auth.php
    participant DB as PostgreSQL
    participant Outbox as Outbox.php
    participant Maintenance as Request shutdown maintenance
    participant Inbox as Mailpit
    User->>UI: Submit registration form
    UI->>Controller: POST /register with CSRF
    Controller->>Auth: Validate input, rate limit, and hash password
    Controller->>DB: Begin transaction and insert unverified user
    Controller->>Auth: Create verification token
    Auth->>DB: Store token hash and expiry
    Auth->>Outbox: Queue confirmation email with raw token link
    Outbox->>DB: Store encrypted payload in same transaction
    Controller->>DB: Commit
    Controller-->>User: Redirect to login with inbox message
    Maintenance->>Outbox: Claim committed delivery job
    Outbox->>Inbox: Deliver through PHP mail and msmtp
    User->>Controller: GET /verify with token
    Controller-->>User: Confirmation form
    User->>Controller: POST /verify with token, purpose, and CSRF
    Controller->>Auth: Consume token and apply confirmation callback
    Auth->>DB: Lock token, mark consumed, and set verified_at atomically
    User->>Controller: POST /login
    Controller->>Auth: Verify password and confirmed account, then log in
    Auth->>Auth: Regenerate session ID and CSRF token
    Controller-->>User: Redirect to editor
```

[`AuthController.php`](src/Http/Controllers/AuthController.php) coordinates account actions. [`Auth.php`](src/Security/Auth.php) owns session cookies, CSRF/origin checks, current-user lookup, login requirements, rate limits, validation, Argon2id hashing, and token creation/consumption. [`SettingsController.php`](src/Http/Controllers/SettingsController.php) coordinates preference changes.

- Sessions live in `storage/sessions`. Cookies hold a session ID. `Auth::user()` checks verification and compares session `auth_version` against the database.
- Sessions expire after 30 minutes idle or 24 hours total. Login/logout regenerate session IDs and CSRF tokens.
- Reset tokens expire after 30 minutes. Confirmation/email-change tokens last 24 hours. `account_tokens` stores hashes, while raw links are included in encrypted email payloads.
- GET `/verify` or `/reset` renders a form. POST consumes the token and applies the change inside a transaction.
- Password reset increments `auth_version` and signs the requester out. Changing a password in settings invalidates other sessions while refreshing the current session.
- Email changes require the current password and confirmation at the new address. Username and notification changes do not require the current password.

## 6. Likes, comments, and request-driven maintenance

[`PhotoController::interact()`](src/Http/Controllers/PhotoController.php) requires login and locks a ready photo in a transaction. Likes use a composite primary key. Comments accept 1–1000 characters and have a unique `(user_id, submission_key)` to deduplicate retries. A new comment queues mail in the same transaction when the owner has notifications enabled. `deleteComment()` checks ownership in its DELETE query.

```mermaid
flowchart LR
    Action[Account action or new comment] --> Transaction[Database transaction]
    Transaction --> Change[(Account or comment change)]
    Transaction --> Queue[(Encrypted outbox job)]
    Shutdown[index.php shutdown hook] --> Maintenance[Maintenance.run]
    Maintenance --> Delivery[Outbox.once claims a due job]
    Queue --> Delivery
    Delivery --> Check[Check expiry and comment eligibility]
    Check --> Send[Email.encode, PHP mail, msmtp]
    Send --> Mailpit[Mailpit SMTP]
    Delivery --> Result[Record completion or retry]
    Maintenance --> Cleanup[PhotoPublisher.cleanup and expired-record cleanup]
```

[`Email.php`](src/Mail/Email.php) builds account/comment messages, renders [`Views/emails/message.php`](src/Views/emails/message.php), and encodes plain-text/HTML mail. [`Outbox.php`](src/Mail/Outbox.php) encrypts payloads with `APP_KEY`, deduplicates events, leases jobs with `FOR UPDATE SKIP LOCKED`, and sends through PHP `mail()`.

[`Maintenance::run()`](src/Maintenance.php) runs at shutdown for non-health application requests with response status below 500. It processes at most five outbox jobs, then uses a PostgreSQL advisory lock to serialize media cleanup and expired rate-limit/token cleanup. There is no separate worker: retries and cleanup depend on application requests.

Mail failures retry with exponential delays starting at 30 seconds and stop after five failed attempts. Comment delivery rechecks that the comment exists, its photo is ready, and its owner still wants notifications. Handled jobs have their payload cleared. Expired/ineligible messages are skipped.

## 7. Database relationships

Defined in [`schema.sql`](src/Database/schema.sql):

```mermaid
erDiagram
    users ||--o{ photos : publishes
    users ||--o{ likes : gives
    users ||--o{ comments : writes
    photos ||--o{ likes : receives
    photos ||--o{ comments : receives
    users ||--o{ account_tokens : owns
    users ||--o{ email_outbox : recipient
    comments o|--o{ email_outbox : may_trigger
    users {
        bigint id PK
        varchar username
        varchar email
        varchar password_hash
        timestamptz verified_at
        integer auth_version
    }
    photos {
        bigint id PK
        bigint user_id FK
        varchar kind
        varchar state
        char media_key UK
        char submission_key
    }
    likes {
        bigint photo_id PK,FK
        bigint user_id PK,FK
    }
    comments {
        bigint id PK
        bigint photo_id FK
        bigint user_id FK
        varchar body
        char submission_key
    }
    account_tokens {
        bigint id PK
        bigint user_id FK
        varchar purpose
        char token_hash UK
        timestamptz expires_at
    }
    email_outbox {
        bigint id PK
        bigint user_id FK
        bigint comment_id FK
        varchar event_key UK
        text payload
        integer attempts
        timestamptz next_attempt_at
    }
    rate_limits {
        char key PK
        integer count
        timestamptz expires_at
    }
```

Selected columns are shown. `rate_limits` is independent: its key is an HMAC of action and subject. Username/email uniqueness is case-insensitive. Photo deletion cascades to likes/comments, and comment deletion cascades to linked outbox rows. Photo and comment submission keys are unique per user.

## 8. Folders, runtime, and infrastructure

```text
src/
├── bootstrap.php                 Class loading, UTC, PHP error conversion
├── App.php                       Routing and shared request checks
├── Config.php                    Environment values and filesystem/URL paths
├── Maintenance.php               Request-driven delivery and cleanup
├── Http/
│   ├── Http.php                  HTML, JSON, redirects, escaping, headers
│   ├── HttpError.php             Expected HTTP failures
│   └── Controllers/              Account, settings, gallery, editor, photo actions
├── Repositories/
│   └── PhotoRepository.php       Shared photo lookup and pagination
├── Database/
│   ├── Database.php              PDO and transactions
│   └── schema.sql                Initial database schema
├── Security/Auth.php             Sessions, passwords, CSRF, tokens, rate limits
├── Media/
│   ├── Images.php                Decode, crop, resize, stickers
│   ├── PhotoPublisher.php        Publishing, files, database state, cleanup
│   └── Gif.php                   Generated-frame animation assembly
├── Mail/                         Email templates/encoding and encrypted outbox
└── Views/                        PHP pages, shared partials, email template
public/
├── index.php                     Front controller and error boundary
└── assets/                       Native JS modules, CSS, images
storage/                          Private runtime files in Docker volume
docker/                          Container build and runtime configuration
```

```mermaid
flowchart TD
    Env[Developer prepares a valid .env] --> Compose[compose.yaml]
    Make[make up] --> Compose
    Compose --> Validation[Config.validateStartup before web servers start]
    Browser[Browser] -->|Default localhost port 8080| Nginx
    subgraph Web[web container]
        Nginx[Nginx] -->|FastCGI port 9000| PHP[PHP-FPM and application]
        PHP --> MSMTP[msmtp]
    end
    PHP --> DB[db container: PostgreSQL 17]
    DB --> DBVolume[(database volume)]
    PHP --> Storage[(media volume mounted at storage)]
    MSMTP -->|SMTP port 1025| Mail[mailpit container]
    Browser -->|Default localhost port 8025| Mail
```

| File | Responsibility |
| --- | --- |
| [compose.yaml](compose.yaml) | Services, ports, health checks, startup dependencies, persistent database/media volumes. |
| [Dockerfile](docker/Dockerfile) | PHP-FPM, Nginx, GD, PostgreSQL PDO, EXIF, mbstring, sodium, msmtp, copied source, and storage setup. |
| [nginx.conf](docker/nginx/nginx.conf) | Serve assets and route app URLs to the only executable PHP entry point, `public/index.php`. |
| [start-web.sh](docker/start-web.sh) | Validate environment, start/supervise Nginx and PHP-FPM, and stop both if either exits. |
| [php.ini](docker/php/php.ini), [fpm.conf](docker/php/fpm.conf) | Runtime/upload limits, session/log paths, PHP mail transport, and FPM settings. |
| [msmtprc](docker/mail/msmtprc) | Route SMTP to Mailpit. |
| [Config.php](src/Config.php), [.env.example](.env.example) | Read and validate developer-supplied environment configuration. Startup requires an existing .env file and rejects missing values or placeholder secrets. |
| [Makefile](Makefile) | Start, stop, and log commands. |

The `media` volume contains the whole `storage/` directory, including media, sessions, temporary files, and PHP logs. PostgreSQL initializes a new database volume using `schema.sql`. Existing volumes require an explicit schema update when the schema changes. This refactor changes no database schema. Source is copied into the image, so rebuild with `make up` after code changes.

## 9. Where to start when changing a feature

| Change | Start here |
| --- | --- |
| Page content/navigation | Relevant `src/Views` template and shared `layout.php`. |
| Visual styling | `public/assets/css/style.css` and `theme.css`. |
| Browser startup | `layout.php` script tag → `app.js` imports/initializers. |
| Camera/editor behavior | `editor.js` and `camera.js`. |
| Stickers | `editor.php`, `editor.js`, `Images::OVERLAYS`, `Images::composite()`, and `public/assets/images/overlays/`. |
| New/changed URL | `App.php` dispatch, then the responsible controller. |
| Gallery/history ordering or pagination | `PhotoRepository::gallery()` and `gallery.js`. |
| Likes/comments/delete | `PhotoController`, the template, and `forms.js`. |
| Sharing | `photo.php` and `photo.js`. |
| Accounts/settings | `AuthController` or `SettingsController`, `Auth.php`, and the matching template/browser module. |
| Media publication | `EditorController::publish()` → `PhotoPublisher::publish()` → `Images` / `Gif`. |
| Email | `Email.php`, `Views/emails/message.php`, `Outbox.php`, and `Maintenance.php`. |

Functions carry short comments explaining their purpose. Comments around non-obvious steps explain retry safety, authorization, file lifecycle, or transaction boundaries.
