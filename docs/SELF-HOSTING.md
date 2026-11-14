# Self-hosting OOTD

OOTD runs anywhere that has PHP 8.3+ and MySQL 8+ or MariaDB 10.6+. There are two ways to run it:
with **Docker**, or on an ordinary **PHP host** (shared hosting, Plesk, cPanel, a VPS).

What you need either way:

- A domain name with HTTPS. Its address (`APP_URL`) becomes part of every public link and,
  once federation arrives, part of every account's identity, so pick it before people sign up.
- A secret `PWD_SEED` (`openssl rand -hex 32`). It peppers every password. Back it up and never
  change it, or nobody can log in.
- Optionally, an SMTP account for email (password resets and invites). Without one, those emails
  are written to `tmp/mail.log` and the admin has to pass links on by hand.

## Docker

```
git clone <the OOTD repository> ootd && cd ootd
cp .env.docker.example .env.docker      # fill it in
docker compose --env-file .env.docker up -d
docker compose --env-file .env.docker logs web   # shows the admin login if you didn't set one
```

This starts three containers: `web` (Apache and PHP on port 8080), `worker` (background jobs)
and `db` (MariaDB). Photos are kept in the `media` volume, the database in `db`. Put a reverse
proxy that handles HTTPS (Caddy, nginx, Traefik) in front of port 8080.

On every start, `web` installs OOTD if the database is empty, or upgrades it to the image's
schema. The CSS is compiled when the image is built.

To upgrade: `git pull`, then `docker compose --env-file .env.docker up -d --build`.

## PHP host

1. Upload the code so that the folder holding `index.php` is the site's web root, and run
   `composer install --no-dev`.
2. Apache: the included `.htaccess` routes requests and blocks every folder that isn't meant to
   be served (`Classes/`, `Functions/`, `vendor/` except TinyMCE, `storage/`, `.env` and so on).
   It needs `mod_rewrite` and `AllowOverride All`. On nginx, send every request that isn't a file
   under `Assets/` or `vendor/tinymce/` to `index.php`, and deny the rest.
3. Create an empty database and a user for it.
4. Install, either from the command line:

   ```
   DB_HOST=localhost DB_USER=ootd DB_PASS=... DB_SCHEMA=ootd APP_URL=https://ootd.example \
   ADMIN_EMAIL=you@example.com php bin/ootd install
   ```

   or by opening `/install.php` in a browser. The browser installer creates `admin` /
   `webmaster@<host>` with password `root`, so change it straight away.
5. Make `storage/` and `tmp/` writable by PHP.
6. Run background jobs. With cron, every minute:

   ```
   * * * * * php /path/to/ootd/bin/cron.php
   ```

   Or keep `php bin/worker.php` running under systemd or supervisor.
7. In `/admin` → Settings, set the server's name, description, rules, contact and who can join,
   and turn **Recompile sass** off.

## Settings in `.env`

| Key | |
| --- | --- |
| `DB_HOST`, `DB_USER`, `DB_PASS`, `DB_SCHEMA` | Database. A port goes in the host: `host;port=3307`. |
| `APP_URL` | The site's address, e.g. `https://ootd.example`. |
| `PWD_SEED` | Password pepper. Never change it. |
| `TINYMCE` | TinyMCE licence key; `gpl` for the self-hosted editor. |
| `MEDIA_DRIVER` | `local` (default) or `s3`. |
| `STORAGE_PATH` | Where the local driver keeps photos, default `storage/`. |
| `S3_ENDPOINT`, `S3_REGION`, `S3_BUCKET`, `S3_KEY`, `S3_SECRET`, `S3_PATH_STYLE` | S3-compatible storage (AWS, Cloudflare R2, Backblaze B2, MinIO). The bucket stays private. |
| `MAIL_HOST`, `MAIL_PORT`, `MAIL_ENCRYPTION`, `MAIL_USER`, `MAIL_PASS`, `MAIL_FROM`, `MAIL_FROM_NAME` | SMTP. `MAIL_ENCRYPTION` is `tls` (port 587), `ssl` (465) or `none`. |
| `CSS_BUILT` | Set to `1` when the CSS is compiled at build time (the Docker image does this). |

## Who can join

`/admin` → Settings → **Registration**:

- **invite** (default): people request an invite at `/signup`; an admin approves them in `/admin`,
  and the approval emails them a signup link.
- **open**: anyone can create an account at `/signup`.
- **closed**: no sign-ups or requests. Invites an admin creates still work.

## Backups

Back up three things: the database, the photos and `.env`.

```
mysqldump --single-transaction ootd > ootd.sql      # or: docker compose exec db mariadb-dump ...
tar czf ootd-media.tgz storage/                     # or the media volume, or your S3 bucket
cp .env ootd.env
```

To restore, put the code and `.env` back, load the dump into an empty database, put `storage/`
back, then run `php bin/ootd migrate` in case the code is newer than the backup.

## Upgrading

1. Back up (above).
2. Replace the code (or `git pull`) and run `composer install --no-dev`.
3. Run `php bin/ootd migrate`, or press **Upgrade database** at `/install.php` as an admin. It's
   safe to run more than once.

Release notes are in [ROADMAP.md](ROADMAP.md).

## Monitoring

- `GET /health` answers `200 {"status":"ok"}` when the database is reachable and up to date,
  storage and `tmp/` are writable, and background jobs are running; otherwise `503`. Point an
  uptime monitor at it.
- `php bin/ootd health` prints the same checks with details.
- `/admin` → Jobs lists failed jobs and can retry them.

## Your users' data

Every account can download its data from `/account` → **Your data**: a zip of JSON files and
photos. That covers GDPR access and portability requests.
