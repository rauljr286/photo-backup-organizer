# Photo Backup Organizer

A private, per-user photo backup and organization web app built with **Laravel 13**, **MySQL**, and a hand-written, WCAG 2.1 AA accessible front end. Upload photos in bulk, organize them into albums, tag and search them, preview/zoom, download or export them, and archive photos you want to hide from your library — plus a token-authenticated JSON API.

## Features

- **Authentication** — email/password registration, login, and logout; signed-in users can change their password from Settings.
- **Bulk upload** — drag-and-drop or file picker, per-file progress, client- and server-side size/type validation, and alt text capture.
- **Organization** — albums (create/rename/delete; deleting an album keeps its photos), tags, search, and date-range filters.
- **Viewing** — responsive photo grid and a detail page with zoom, metadata, alt-text editing, tags, and album assignment.
- **Archive** — move photos you want to keep but hide to an Archive page with an Undo toast; you can restore them at any time. Users cannot permanently delete their own photos.
- **Download & export** — single-photo download and per-album ZIP export.
- **Storage meter** — a per-user quota (default 5 GB) shown as an accessible meter.
- **Cloud backup** — "Back Up Now" pushes photos to an S3-compatible disk, or mirrors them locally when no cloud disk is configured.
- **Legal pages** — in-app Privacy Policy and Terms of Service.
- **JSON API** — Sanctum-bearer-token endpoints for every core action.

## Tech stack

| Layer      | Choice                                              |
| ---------- | --------------------------------------------------- |
| Framework  | Laravel 13 (PHP 8.3)                                |
| Database   | MySQL 8 (Laragon)                                   |
| Auth       | Session guard (web) + Laravel Sanctum tokens (API)  |
| Front end  | Blade + hand-written CSS/JS bundled with Vite       |
| Storage    | Laravel `public` disk for originals, optional cloud |

## Requirements

- [Laragon](https://laragon.org/) with PHP 8.3+ and MySQL 8
- Composer
- Node.js 20+ (for building assets)

### Required PHP extensions

`curl`, `fileinfo`, `gd`, `mbstring`, `exif`, `mysqli`, `openssl`, `pdo_mysql`, `pdo_sqlite` (tests), `sqlite3` (tests), `sodium`, `zip`.

### Required PHP configuration (`php.ini`)

Bulk uploads send several photos in one request, so PHP's upload limits must be
raised above the defaults. Set these in your active `php.ini` (in Laragon:
`C:\laragon\bin\php\php-<version>\php.ini`) and restart the web server / run
`laragon reload` after editing:

| Setting              | Required    | Default (PHP) | Why                                                            |
| -------------------- | ----------- | ------------- | -------------------------------------------------------------- |
| `max_file_uploads`   | `200`       | `20`          | Accept more than 20 files per upload request.                  |
| `upload_max_filesize`| `64M`       | `2M`          | Accept individual photos up to the app limit (20MB).           |
| `post_max_size`      | `128M`      | `8M`          | Accept a full batch of photos in one request.                  |
| `max_execution_time` | `300`       | `30`          | Give large batches time to process without timing out.         |
| `memory_limit`       | `256M`      | `128M`        | Process several photos per request without exhausting memory.  |

> The front end also uploads in chunks of 10 files (~96MB) per request, which
> keeps batches comfortably below these limits even when every photo is near the
> per-file maximum. A single bad file is rejected on its own — it never fails the
> rest of a batch — and real server-side exceptions are written to
> `storage/logs/laravel.log`.

## Setup

1. **Create the database** (Laragon defaults to user `root` with no password):

   ```sql
   CREATE DATABASE photo_backup CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```

   In Laragon you can run this from **Database → HeidiSQL/phpMyAdmin**, or:

   ```shell
   mysql -u root -e "CREATE DATABASE photo_backup CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
   ```

2. **Install dependencies and configure the environment**:

   ```shell
   composer install
   copy .env.example .env
   php artisan key:generate
   ```

   Adjust `DB_*` in `.env` if your MySQL user/password differ.

3. **Build front-end assets and migrate**:

   ```shell
   npm install
   npm run build
   php artisan migrate
   php artisan storage:link
   ```

4. **Serve the app** — start Laragon (Apache/Nginx + MySQL), then visit the vhost, or run:

   ```shell
   php artisan serve
   ```

5. Register an account at `/register` and start uploading.

## Environment variables

Beyond the standard Laravel keys, the app reads:

| Variable                     | Default                | Purpose                                             |
| ---------------------------- | ---------------------- | --------------------------------------------------- |
| `PHOTOS_CLOUD_DISK`          | *(empty)*              | Disk name for backups, e.g. `s3`. Empty = local mirror. |
| `PHOTOS_LOCAL_MIRROR_DISK`   | `local`                | Disk used to mirror backups when no cloud disk is set. |
| `PHOTOS_MAX_UPLOAD_MB`       | `20`                   | Maximum size of a single uploaded photo (MB).       |
| `PHOTOS_SUPPORT_EMAIL`       | `support@example.com`  | Support address shown on legal pages.               |

### Enabling real cloud backup

Set `PHOTOS_CLOUD_DISK=s3` and configure the `AWS_*` keys, then add an `s3` disk in `config/filesystems.php` (or install `league/flysystem-aws-s3-v3`). With no cloud disk configured, "Back Up Now" mirrors files to the `local` (private) disk so the flow works offline.

## Deploying to Render (free)

The repository ships a `render.yaml` blueprint: a Docker web service (PHP 8.3 +
Apache + PostgreSQL) and a free Postgres database. Push the repo to GitHub,
then in Render use **New → Blueprint** and select the repository — the
blueprint provisions the database, builds the Dockerfile, and deploys.

1. **Create a repository** on [GitHub](https://github.com) and push this folder
   (Git is not required — GitHub's web upload works too). `.env`, `vendor/`,
   `node_modules/`, `public/build` (compiled during the image build) and
   `storage/app/*` are excluded automatically.
2. **Deploy:** Render → **New → Blueprint** → pick the repo. Wait for the build
   and the `php artisan migrate` step (part of the container startup).
3. **Fix `APP_URL`** in the service's **Environment** tab so it matches the
   exact subdomain Render assigned (e.g. `https://photo-backup-organizer.onrender.com`),
   then redeploy.
4. Open the live URL and register an account.

Free-tier notes:

- The **free Postgres database expires after 30 days**. Before it expires,
  `pg_dump` it, or create a free database on
  [Neon](https://neon.tech) and paste its connection string into the service's
  `DB_URL` environment variable instead.
- Free web services spin down after ~15 minutes without traffic and take about
  a minute to restart on the next request (the `/up` health route handles
  Render's health checks).
- Uploads are stored in the container's ephemeral disk, so they are lost on a
  new deploy. For persistence set `PHOTOS_CLOUD_DISK=s3` and the matching
  `AWS_*` keys (see "Enabling real cloud backup").

## Deploying to Railway

The repo ships a **`Dockerfile`** (PHP 8.3 + Apache + MySQL). Railway
builds and runs that Dockerfile, so **this route does not use Railpack**. It
also means you do **not** set a custom build command or pre-deploy command —
the image builds the front-end assets and the container runs the migrations
itself (details below). Railway still serves the app behind its own proxy with
HTTPS, and the repo is configured for that:

- `bootstrap/app.php` trusts the proxy headers (`trustProxies(at: '*')`).
- `AppServiceProvider` forces the `https` scheme in production so generated
  URLs never become mixed content.
- The image raises the PHP upload limits (via `uploads.ini`:
  `upload_max_filesize=64M`, `post_max_size=128M`, `max_file_uploads=200`,
  `memory_limit=256M`, `max_execution_time=300`) so bulk uploads do not fail
  with HTTP 413 or "No photos were received", and `npm run build` compiles the
  Vite assets into `public/build` during the image build.
- On **every container start** the entrypoint (`docker/entrypoint.sh`): makes
  Apache listen on Railway's `PORT`, re-creates and chowns the storage tree
  (a mounted Volume comes up root-owned), runs `php artisan migrate --force`
  and `php artisan storage:link --force`.
- Photos, thumbnails and avatars are stored via the `public` disk
  (`storage/app/public`) with relative paths, and are streamed back through
  controller routes backed by the Storage facade — nothing depends on a
  symlink surviving the build.
- There are **no queued jobs or queued mail listeners**, so `QUEUE_CONNECTION=sync`
  runs everything (thumbnail generation, mirror backup, login logging) inside
  the request — no worker service is required.

> The project-root `php.ini` and the `RAILPACK_PHP_EXTENSIONS` variable apply
> **only to Railpack** and are **not used** when building the Dockerfile — the
> upload limits above are baked into the image instead.

### 1. Create the Railway project and database

Push this repo to GitHub and create a new project in Railway from it. Add a
**MySQL** service (Railway provisions a MySQL 8 service). Copy the connection
string it shows you — that is the `DB_URL` value.

### 2. Environment variables

Set these in **Variables** (names and example values only — real secrets go in
Railway's variable editor, never in the repo):

| Variable                     | Example value                             | Purpose                                          |
| ---------------------------- | ----------------------------------------- | ------------------------------------------------ |
| `APP_NAME`                   | `Photo Backup Organizer`                  | Shown in the title bar and emails                |
| `APP_ENV`                    | `production`                              | Production behaviour (no debug)                  |
| `APP_DEBUG`                  | `false`                                   | Never true in production                         |
| `APP_URL`                    | `https://<your-project>.up.railway.app`   | Public URL used to build absolute links          |
| `APP_KEY`                    | `base64:...` (run `php artisan key:generate --show`) | Application encryption key            |
| `DB_CONNECTION`              | `mysql`                                   | Database driver                                  |
| `DB_URL`                     | `mysql://user:password@host:3306/railway` | Full connection string from the MySQL service    |
| `SESSION_DRIVER`             | `database`                                | Sessions stored in MySQL (`sessions` table)      |
| `CACHE_STORE`                | `database`                                | Cache stored in MySQL (`cache` table)            |
| `QUEUE_CONNECTION`           | `sync`                                    | Run jobs inline — no worker needed               |
| `LOG_CHANNEL`                | `stderr`                                  | Logs go to stdout/stderr, visible in Railway     |
| `MAIL_MAILER`                | `log`                                     | Write mail to the log instead of sending (see below) |
| `MAIL_FROM_ADDRESS`          | `support@example.com`                     | From address for any email the app sends         |
| `PHOTOS_SUPPORT_EMAIL`       | `support@example.com`                     | Support address shown on the legal pages         |
| `PHOTOS_MAX_UPLOAD_MB`       | `20`                                      | Per-photo size cap (must stay under 64M)         |

Optional:

| Variable                     | Purpose                                                        |
| ---------------------------- | -------------------------------------------------------------- |
| `PHOTOS_CLOUD_DISK`          | `s3` to mirror "Back Up Now" uploads to an S3-compatible bucket |
| `AWS_*`                      | Credentials/bucket for the S3 disk                             |
| `GOOGLE_CLIENT_ID/SECRET`    | Google sign-in (redirect: `{APP_URL}/auth/google/callback`)    |
| `FACEBOOK_CLIENT_ID/SECRET`  | Facebook sign-in                                               |

Notes:

- **`APP_URL`** must be the exact live URL (see step 4 for the public domain).
- **`APP_KEY`** — generate one once with `php artisan key:generate --show`
  locally (or `php -r "echo base64_encode(random_bytes(32));"`) and paste the
  `base64:...` value into `APP_KEY`. Do not leave it empty.
- **`DB_URL`** is the single source of truth for the connection; `DB_HOST`,
  `DB_PORT`, `DB_DATABASE`, `DB_USERNAME` and `DB_PASSWORD` are **not** needed
  on Railway.
- **`PORT`** is injected automatically by Railway — do **not** set it by hand.
  The container entrypoint makes Apache listen on it (falling back to `80`).
- If you add OAuth, copy the provider's authorised redirect URI from the
  provider console using your live `APP_URL`.

### 3. No build or pre-deploy commands needed

Because the repo contains a `Dockerfile`, Railway builds that image and you
leave **Settings → Deploy** empty:

- `npm run build` runs **during the image build**, so `public/build` is
  produced inside the image (it is git-ignored and never committed).
- `php artisan migrate --force` runs **at container start** from
  `docker/entrypoint.sh`, together with `storage:link --force`.

Setting a build command or pre-deploy command is unnecessary (and the
Railpack-era `npm run build && php artisan storage:link --force` commands no
longer apply).

### 4. Public domain

On your service's **Settings → Networking**: **Generate Domain** (a `.up.railway.app`
URL), or add a **Custom Domain**. Put that exact URL in `APP_URL`, then redeploy.

### 5. Persist uploaded photos (ephemeral filesystem)

Railway's filesystem is **ephemeral** — anything written to `storage/app`
during a deploy is lost on the next deploy. To keep users' uploads, either:

- **Attach a Volume** to the service and mount it at
  **`/var/www/html/storage/app`** (the container's working directory is
  `/var/www/html`, so this is Laravel's `storage_path('app')`). The startup
  script re-creates and chowns the storage tree on every boot, so the
  root-owned volume is writable by PHP; or
- **Use an S3-compatible bucket**: set `PHOTOS_CLOUD_DISK=s3`, fill in the
  `AWS_*` variables, and install the Flysystem adapter
  (`composer require league/flysystem-aws-s3-v3`).

Without either, avatars, photos and thumbnails survive until the next deploy
but are then wiped (the database keeps the metadata pointing at missing files).

### 6. Reading the password reset email

With `MAIL_MAILER=log`, Laravel writes every outgoing email to the default log
channel instead of sending it. With `LOG_CHANNEL=stderr` (as above) that means
the full message — including the password reset link — is printed to the
service logs:

1. Open the service in Railway and click **Deployments**.
2. Select the running deployment and open the **Logs** panel (or **View Logs**).
3. Trigger "forgot password" in the app; the reset email appears in the logs.

For real delivery, switch `MAIL_MAILER` to `smtp` (plus `MAIL_HOST`,
`MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`) or a provider mailer such as
`resend`, `postmark` or `ses`.

## Testing

The suite runs against an in-memory SQLite database (configured in `phpunit.xml`) and does not require MySQL or built assets:

```shell
php artisan test
```

Coverage includes authentication (web + API), uploads and validation, ownership/authorization, archive → restore, search and tag filtering, cloud-mirror backup, and album create/rename/delete/ZIP export.

## API

Base path `/api`. Public: `POST /api/register`, `POST /api/login`. All other routes require `Authorization: Bearer <token>`.

| Method | Endpoint                              | Description                       |
| ------ | ------------------------------------- | --------------------------------- |
| POST   | `/api/register`                       | Register and receive a token      |
| POST   | `/api/login`                          | Log in and receive a token        |
| POST   | `/api/logout`                         | Revoke the current token          |
| GET    | `/api/user`                           | Profile and storage usage         |
| GET    | `/api/photos`                         | List/filter photos                |
| POST   | `/api/photos`                         | Upload one or more photos         |
| GET    | `/api/photos/{photo}`                 | Photo details                     |
| GET    | `/api/photos/{photo}/raw`             | Stream the image                  |
| DELETE | `/api/photos/{photo}`                 | Move to archive                  |
| POST   | `/api/photos/{photo}/unarchive`       | Restore from archive             |
| GET    | `/api/albums`                         | List albums                       |
| POST   | `/api/albums`                         | Create an album                   |
| PUT    | `/api/albums/{album}`                 | Rename an album                   |
| DELETE | `/api/albums/{album}`                 | Delete an album (keeps photos)    |
| POST   | `/api/albums/{album}/photos/{photo}`  | Add a photo to an album           |
| DELETE | `/api/albums/{album}/photos/{photo}`  | Remove a photo from an album      |

## Migrating legacy trashed photos

Photos soft-deleted before the archive system existed can be moved to the archive with:

```shell
php artisan photos:migrate-trashed-to-archived
```

## Theming (light / dark)

The whole interface ships with a light and a dark theme driven by CSS custom
properties. Light values live on `:root`; the dark overrides live under
`[data-theme='dark']` (both in `resources/css/app.css`).

- **Default** follows the operating system via `prefers-color-scheme`.
- **Manual choice** is stored in `localStorage` under the key `pbo-theme`
  (`'light'` or `'dark'`) and overrides the OS setting.
- **No flash**: an inline script in the document `<head>` (the
  `<x-theme-head />` component, included by every layout) resolves and applies
  the theme before the first paint; `color-scheme` is set in CSS and via a
  `<meta name="color-scheme">` tag so native controls match.
- **Toggle**: the reusable `<x-theme-toggle />` component renders an accessible
  sun/moon button (44px touch target, changing `aria-label`/`aria-pressed`). It
  appears in the app navbar, in the mobile sidebar menu (labelled), and in the
  landing header. Because the preference is shared, the guest/auth pages use the
  resolved theme without rendering their own control. Multiple tabs stay in sync
  through the `storage` event.
- **Transitions**: surfaces cross-fade for ~200ms on theme change, and are
  instant under `prefers-reduced-motion: reduce`.

A few decorative colours are intentionally fixed and do not change with the
theme: the teal-to-mint brand gradients (header, hero, brand buttons), the
illustrated camera on the landing page, the album accent bars, the large
photo-viewer stage, and the lightbox backdrop. User photos and thumbnails are
never filtered, tinted or dimmed by either theme.

## Accessibility

The UI targets **WCAG 2.1 AA**: a skip link, a visible 3px focus outline, labelled form controls, descriptive alt text on every meaningful image, action-based button labels, `aria-live` status toasts, native `<dialog>` confirmations, a `role="meter"` storage bar, password show/hide toggles with `aria-pressed` state, semantic headings, and a contrast-checked color palette. All colors live as custom properties in `resources/css/app.css` (`:root` for light, `[data-theme='dark']` for dark), with a teal-to-mint gradient identity for the header, buttons, badges, and guest pages; every text/background pairing used in both themes is checked against the 4.5:1 / 3:1 contrast ratios.

## Project structure

```
app/
├── Console/Commands/MigrateTrashedPhotosToArchived.php   # one-time deleted_at → archived_at migration
├── Http/
│   ├── Controllers/Api/                     # token-authenticated JSON API
│   ├── Controllers/Web/                     # session-based web UI
│   └── Requests/                            # form request validation
├── Models/{User,Photo,Album}.php
└── Services/PhotoStorageService.php         # all upload/backup/query logic
config/photobackup.php                        # app-specific configuration
resources/{css,js,views}/                     # accessible front end
routes/{web,api,console}.php
tests/Feature/                                # auth, photo and album tests
```
