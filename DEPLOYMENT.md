# Deployment Guide

## 1. Push to GitHub

The local repo is already initialized and committed. To push it:

```bash
git remote add origin https://github.com/<your-username>/<your-repo>.git
git branch -M main
git push -u origin main
```

## 2. Deploy to Hostinger (shared hosting / hPanel)

Laravel's entry point is `public/index.php`, but Hostinger's default web root for a domain is `public_html`. Which setup you use depends on your plan.

### Option A — Custom document root (Business/Cloud shared plans)

If hPanel lets you set a custom document root for the domain:

1. In hPanel, use the **Git** feature (or SSH) to clone your GitHub repo into a folder above `public_html`, e.g. `~/pos-inventory-system`.
2. In hPanel → **Websites → Manage → Advanced → Document Root**, point the domain at `~/pos-inventory-system/public`.
3. No file restructuring needed — skip to [Common server setup](#common-server-setup) below.

### Option B — No document root control (basic shared plans)

1. Upload the full project to a folder **outside** `public_html`, e.g. `~/pos-inventory-system` (via Git in hPanel, SSH + git clone, or FTP).
2. Copy the **contents** of `pos-inventory-system/public/` into `public_html/` (not the `public` folder itself — its contents).
3. Edit `public_html/index.php` and update the two `require` paths to point at the app folder:

   ```php
   require __DIR__.'/../pos-inventory-system/vendor/autoload.php';

   $app = require_once __DIR__.'/../pos-inventory-system/bootstrap/app.php';
   ```

4. Continue to [Common server setup](#common-server-setup) below.

### Common server setup

1. **PHP version** — in hPanel → **Advanced → PHP Configuration**, select PHP 8.2 or newer (this app requires `^8.2`).
2. **Database** — in hPanel → **Databases → MySQL Databases**, create a database and user (Hostinger prefixes both with your account ID, e.g. `u123456789_pos`). Note the host (usually `localhost`), database name, username, and password.
3. **Environment file** — copy `.env.example` to `.env` on the server and fill in:
   ```
   APP_ENV=production
   APP_DEBUG=false
   APP_URL=https://yourdomain.com

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
