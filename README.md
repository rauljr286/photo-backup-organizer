# Photo Backup Organizer

A private, per-user photo backup and organization web app built with **Laravel 13**, **MySQL**, and a hand-written, WCAG 2.1 AA accessible front end. Upload photos in bulk, organize them into albums, tag and search them, preview/zoom, download or export them, and recover deleted photos from a 30-day trash — plus a token-authenticated JSON API.

## Features

- **Authentication** — email/password registration, login, logout, and password reset (reset link is written to the log in local development via `MAIL_MAILER=log`).
- **Bulk upload** — drag-and-drop or file picker, per-file progress, client- and server-side size/type validation, and alt text capture.
- **Organization** — albums (create/rename/delete; deleting an album keeps its photos), tags, search, and date-range filters.
- **Viewing** — responsive photo grid and a detail page with zoom, metadata, alt-text editing, tags, and album assignment.
- **Trash** — deletes are reversible for 30 days with an Undo toast; permanent deletion requires typing `DELETE`. A scheduled command purges expired items.
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
| `PHOTOS_TRASH_RETENTION_DAYS`| `30`                   | How long trashed photos are kept before purging.    |
| `PHOTOS_MAX_UPLOAD_MB`       | `20`                   | Maximum size of a single uploaded photo (MB).       |
| `PHOTOS_SUPPORT_EMAIL`       | `support@example.com`  | Support address shown on legal pages.               |

### Enabling real cloud backup

Set `PHOTOS_CLOUD_DISK=s3` and configure the `AWS_*` keys, then add an `s3` disk in `config/filesystems.php` (or install `league/flysystem-aws-s3-v3`). With no cloud disk configured, "Back Up Now" mirrors files to the `local` (private) disk so the flow works offline.

## Testing

The suite runs against an in-memory SQLite database (configured in `phpunit.xml`) and does not require MySQL or built assets:

```shell
php artisan test
```

Coverage includes authentication (web + API), uploads and validation, ownership/authorization, trash → restore → purge, search and tag filtering, cloud-mirror backup, and album create/rename/delete/ZIP export.

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
| DELETE | `/api/photos/{photo}`                 | Move to trash                     |
| POST   | `/api/photos/{photo}/restore`         | Restore from trash                |
| GET    | `/api/albums`                         | List albums                       |
| POST   | `/api/albums`                         | Create an album                   |
| PUT    | `/api/albums/{album}`                 | Rename an album                   |
| DELETE | `/api/albums/{album}`                 | Delete an album (keeps photos)    |
| POST   | `/api/albums/{album}/photos/{photo}`  | Add a photo to an album           |
| DELETE | `/api/albums/{album}/photos/{photo}`  | Remove a photo from an album      |

## Trash purge schedule

`photos:purge-trash` permanently deletes photos whose retention window has elapsed. It is scheduled daily in `routes/console.php`; run it manually with:

```shell
php artisan photos:purge-trash
```

For scheduled commands to run automatically, ensure the Laravel scheduler is executed every minute (`php artisan schedule:run` via Task Scheduler/Laragon cron), or use `php artisan schedule:work` during development.

## Accessibility

The UI targets **WCAG 2.1 AA**: a skip link, a visible 3px focus outline, labelled form controls, descriptive alt text on every meaningful image, action-based button labels, `aria-live` status toasts, native `<dialog>` confirmations, a `role="meter"` storage bar, password show/hide toggles with `aria-pressed` state, semantic headings, and a contrast-checked color palette. All colors live in `:root` custom properties in `resources/css/app.css` (an indigo-violet gradient identity for the header, buttons, badges, and guest pages; every text/background pairing used is checked against the 4.5:1 / 3:1 contrast ratios).

## Project structure

```
app/
├── Console/Commands/PurgeTrashCommand.php   # scheduled trash purge
├── Http/
│   ├── Controllers/Api/                     # token-authenticated JSON API
│   ├── Controllers/Web/                     # session-based web UI
│   └── Requests/                            # form request validation
├── Mail/PasswordResetLink.php
├── Models/{User,Photo,Album}.php
└── Services/PhotoStorageService.php         # all upload/backup/query logic
config/photobackup.php                        # app-specific configuration
resources/{css,js,views}/                     # accessible front end
routes/{web,api,console}.php
tests/Feature/                                # auth, photo and album tests
```
