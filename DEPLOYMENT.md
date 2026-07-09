# Deployment Guide

## 1. Push to GitHub

The local repo is already initialized and committed. To push it:

```bash
git remote add origin https://github.com/eyetea2026-commits/posinventory.git
git branch -M main
git push -u origin main
```

Already done — the repo is live at https://github.com/eyetea2026-commits/posinventory.

## 2. Deploy to Hostinger (shared hosting / hPanel)

Laravel's entry point is `public/index.php`, but Hostinger's default web root for a domain is `public_html`. Which setup you use depends on your plan.

### Option A — Custom document root (Business/Cloud shared plans)

If hPanel lets you set a custom document root for the domain:

1. In hPanel, use the **Git** feature (or SSH) to clone your GitHub repo into a folder above `public_html`, e.g. `~/posinventory`.
2. In hPanel → **Websites → Manage → Advanced → Document Root**, point the domain at `~/posinventory/public`.
3. No file restructuring needed — skip to [Common server setup](#common-server-setup) below.

### Option B — Symlink `public_html` (no document-root UI, but SSH is available — recommended for our plan)

Cleaner than copying files: `public_html` becomes a symlink pointing straight at the app's `public/` folder, so the rest of the app (including `.env`) never lives in the web-accessible directory, and `php artisan storage:link` works normally.

1. SSH into the server.
2. Clone the repo outside `public_html`: `git clone https://github.com/eyetea2026-commits/posinventory.git ~/posinventory`
3. Back up the existing `public_html` (don't delete it outright): `mv public_html public_html_backup`
4. Symlink it to the app's public folder: `ln -s ~/posinventory/public ~/public_html`
5. Continue to [Common server setup](#common-server-setup) below.

If the host doesn't follow symlinks for the document root (rare, but possible on some configs), fall back to the copy approach instead:

1. Copy the **contents** of `posinventory/public/` into `public_html/` (not the `public` folder itself — its contents).
2. Edit `public_html/index.php` and update the two `require` paths to point at the app folder:

   ```php
   require __DIR__.'/../posinventory/vendor/autoload.php';

   $app = require_once __DIR__.'/../posinventory/bootstrap/app.php';
   ```
3. Manually symlink storage after running `php artisan storage:link` on the app folder: `ln -s ~/posinventory/storage/app/public ~/public_html/storage` (the artisan command only links inside `posinventory/public`, which isn't served in this fallback).

### Common server setup

1. **PHP version** — in hPanel → **Advanced → PHP Configuration**, select PHP 8.2 or newer (this app requires `^8.2`).
2. **Database** — in hPanel → **Databases → MySQL Databases**, create a database and user (Hostinger prefixes both with your account ID, e.g. `u123456789_pos`). Note the host (usually `localhost`), database name, username, and password.
3. **Environment file** — copy `.env.example` to `.env` on the server and fill in:
   ```
   APP_ENV=production
   APP_DEBUG=false
   APP_URL=https://cctvexpresstacurong.com

   DB_CONNECTION=mysql
   DB_HOST=localhost
   DB_DATABASE=u123456789_pos
   DB_USERNAME=u123456789_pos
   DB_PASSWORD=your-real-password
   ```
4. **Install PHP dependencies**:
   - If SSH is available: `composer install --no-dev --optimize-autoloader` on the server.
   - If not: run it locally, then upload the generated `vendor/` folder via FTP (it's gitignored, so it won't come from `git pull`).
5. **Build frontend assets** — run locally (Hostinger shared hosting usually doesn't have Node):
   ```bash
   npm ci
   npm run build
   ```
   Then upload the generated `public/build/` folder (Option A) or `public_html/build/` (Option B).
6. **Generate app key**: `php artisan key:generate --force`
7. **Run migrations**: `php artisan migrate --force`
8. **Cache config for production**:
   ```bash
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   ```
9. **Link storage** (for future file uploads): `php artisan storage:link`
10. **Permissions** — ensure `storage/` and `bootstrap/cache/` are writable by the web server (typically `755`/`775`, owned by your hosting user).

### Notes

- This app has no queued jobs or scheduled tasks yet, so no queue worker or cron setup is required right now. If you add either later, set up a cron entry in hPanel for `php artisan schedule:run` (every minute) and consider `QUEUE_CONNECTION=sync` if you don't want to run a persistent queue worker on shared hosting.
- After any future code change: re-run `composer install --no-dev`, `npm run build`, `php artisan migrate --force`, and re-run the three `artisan ...:cache` commands (or `php artisan optimize:clear` during troubleshooting).
