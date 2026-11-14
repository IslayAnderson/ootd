# Self-hosting OOTD

OOTD runs anywhere that has PHP 8.3+ and MySQL 8+ or MariaDB 10.6+. There are two ways to run it:
with **Docker**, or on an ordinary **PHP host** (shared hosting, Plesk, cPanel, a VPS).

What you need either way:

- A domain name with HTTPS. Its address (`APP_URL`) becomes part of every public link and,
  once federation arrives, part of every account's identity, so pick it before people sign up.
- A secret `PWD_SEED` (`openssl rand -hex 32`). It peppers every password. Back it up and never
  change it, or nobody can log in.
- Email for password resets and invites. On most PHP hosts (Plesk, cPanel, a server with
  Postfix) it works with no setup: OOTD sends through the server's own `sendmail`. Otherwise give
  it an SMTP account. With neither (Docker without `MAIL_HOST`, say), emails are written to
  `tmp/mail.log` and the admin has to pass links on by hand.

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
| `S3_ENDPOINT`, `S3_REGION`, `S3_BUCKET`, `S3_KEY`, `S3_SECRET`, `S3_PATH_STYLE` | S3-compatible storage (Cloudflare R2, AWS, Backblaze B2, MinIO). The bucket stays private. See [Photos on Cloudflare R2](#photos-on-cloudflare-r2). |
| `MAIL_DRIVER` | `smtp`, `mail` (PHP's mail(), through the server's sendmail) or `log` (write to `tmp/mail.log`). Default: `smtp` when `MAIL_HOST` is set, else `mail` when sendmail exists, else `log`. |
| `MAIL_HOST`, `MAIL_PORT`, `MAIL_ENCRYPTION`, `MAIL_USER`, `MAIL_PASS` | SMTP. `MAIL_ENCRYPTION` is `tls` (port 587), `ssl` (465) or `none`. |
| `MAIL_FROM`, `MAIL_FROM_NAME` | Sender. Default `noreply@` the site's domain and the instance name. |
| `ACCOUNT_DOMAIN` | Addresses use this domain instead of the site's (`name@example.com` for a site at `blog.example.com`). See [Addresses on another domain](#addresses-on-another-domain). Set it before the site is shared. |
| `FRAME_ANCESTORS` | Sites allowed to show this one in a frame, besides itself, e.g. `"https://example.com https://www.example.com"`. Default: none (only itself). |
| `OLD_HOSTS` | Addresses the site used to have, comma-separated and quoted, e.g. `"ootd.old.example, old.example"`. They redirect (301) to the same page at `APP_URL`. |
| `CSS_BUILT` | Set to `1` when the CSS is compiled at build time (the Docker image does this). |

## Photos on Cloudflare R2

Photos can live in an S3-compatible bucket instead of on the server's disk. Cloudflare R2 is the
recommended provider: there are no fees for serving files, which matters because every photo is
served through OOTD.

1. In the Cloudflare dashboard, go to **R2** → **Create bucket**. Leave public access **off**:
   OOTD checks who may see each photo and serves it itself.
2. **R2** → **Manage API tokens** → **Create API token**, with **Object Read & Write** on that
   bucket only. Note the access key ID, the secret, and the S3 endpoint
   (`https://<account id>.r2.cloudflarestorage.com`).
3. Add to `.env`:

   ```
   MEDIA_DRIVER=s3
   S3_ENDPOINT=https://<account id>.r2.cloudflarestorage.com
   S3_BUCKET=ootd-media
   S3_KEY=<access key id>
   S3_SECRET=<secret access key>
   ```

   `S3_REGION` (`auto`) and `S3_PATH_STYLE` (on) are set automatically for R2 endpoints. For a
   bucket in the EU jurisdiction, use the `https://<account id>.eu.r2.cloudflarestorage.com` endpoint.
4. Run `php bin/ootd storage:check`. It writes a test file, reads it back and deletes it.

Photos already on disk aren't moved. Copy `storage/` into the bucket keeping the same paths
(for example with `rclone copy storage/ r2:ootd-media`) before switching. Otherwise old photos
won't load.

For AWS S3, set `S3_ENDPOINT=https://s3.<region>.amazonaws.com` and `S3_REGION`. For MinIO and
others, also set `S3_PATH_STYLE=1` if they don't support bucket subdomains.

## Who can join

`/admin` → Settings → **Registration**:

- **invite** (default): people request an invite at `/signup`; an admin approves them in `/admin`,
  and the approval emails them a signup link.
- **open**: anyone can create an account at `/signup`.
- **closed**: no sign-ups or requests. Invites an admin creates still work.

## Other blogs

Each OOTD install presents itself to its members as a blog, and other servers (other OOTD installs,
Mastodon and the rest of the Fediverse) as other blogs. Give yours a name in `/admin` → Settings →
**Blog name**, and under **Logo** choose the OOTD wordmark, upload an image, or show the name as text: other OOTD blogs show your members as "mia from *Blog name*". While it is still
the default ("OOTD"), they show your domain instead.

**Connect with other blogs** turns ActivityPub on or off. When it's on, members can follow
people on other blogs (search for "name from Blog name", or their address `name@their.domain`, on
the feed), and those people's posts and photos are copied here. **Space for photos from other
blogs** caps the space their photos take (default 1024 MB). The daily job drops the oldest photos past it, and those posts then link to the original.
The worker or cron must be running for photos to arrive.

## Backups

Back up three things: the database, the photos and `.env`.

```
mysqldump --single-transaction ootd > ootd.sql      # or: docker compose exec db mariadb-dump ...
tar czf ootd-media.tgz storage/                     # or the media volume, or your S3 bucket
cp .env ootd.env
```

To restore, put the code and `.env` back, load the dump into an empty database, put `storage/`
back, then run `php bin/ootd migrate` in case the code is newer than the backup.

## Addresses on another domain

To give people `name@example.com` while OOTD runs at `blog.example.com`:

1. On `example.com`, redirect `/.well-known/webfinger` and `/.well-known/host-meta` (query string
   included) to the same paths on `blog.example.com`. With Apache:

   ```apache
   RewriteEngine On
   RewriteRule ^\.well-known/(webfinger|host-meta)$ https://blog.example.com/.well-known/$1 [R=301,L]
   ```

2. Set `ACCOUNT_DOMAIN=example.com` in `.env`.

Account ids stay at `blog.example.com`, so follows made before keep working; the old address
`name@blog.example.com` still finds the same person. Only one blog can use a domain this way.

## Moving to a new address

1. Serve the same folder at the new address (in Plesk, add the domain to the subscription with the
   same document root) and give it a certificate.
2. Set `APP_URL` to the new address and put the old one in `OLD_HOSTS`. Keep the old address
   pointing at the server so its links keep working.
3. Accounts' addresses change with it (`name@new.host`). People following them from other blogs
   or Mastodon have to follow the new address; OOTD doesn't send a `Move` yet.

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

## Federation

From 0.11.0 every account has an address, `username@your.host`, shown on its profile ("Address for
other blogs"); Mastodon writes it `@username@your.host`.
People on Mastodon and other ActivityPub servers can search for it, follow it and see its public
and followers-only posts. Members and Only me posts never leave your server.

- It needs the site on HTTPS at its final address (`APP_URL`): other servers only talk https, and
  the address is part of every account's identity.
- Delivery happens in background jobs, so the worker or cron job must be running.
- `/admin` → Settings → **Federation** turns it off.
- `/.well-known/` must reach OOTD. Some hosts keep that folder for certificates; only
  `/.well-known/acme-challenge/` is theirs.

## Cloud blogs

From 0.14.0 a blog on a Plesk server can offer **Create my own blog** on its login page. Each
blog it makes is a separate OOTD install, in its own Plesk subscription, at `name.<your domain>`.
It stays off until the first five keys are in `.env`:

| Key | What |
| --- | --- |
| `CLOUD_DOMAIN` | the domain new blogs go under, e.g. `treatmag.online` |
| `CLOUD_SERVER_IP` | the Plesk server's public IP (subscriptions and DNS records point at it) |
| `PLESK_API_URL` | `https://<server's public IP>:8443/enterprise/control/agent.php`: not 127.0.0.1, or the call comes from 127.0.0.1 and a key limited to the public IP is refused |
| `PLESK_API_KEY` | the reseller's secret key (not the admin's) |
| `CLOUD_PLAN` | the reseller's service plan, e.g. `"OOTD Blog"` (quote names with spaces) |
| `PLESK_VERIFY_TLS` | `0` if the panel's certificate is for another name (optional) |
| `CLOUDFLARE_ZONE_ID`, `CLOUDFLARE_API_TOKEN` | only if `CLOUD_DOMAIN`'s DNS is at Cloudflare (optional) |
| `CLOUD_DAILY_LIMIT` | new blogs a day in all (optional, default 10) |
| `CLOUD_HTTPS_WAIT_HOURS` | how long a build waits for a certificate (optional, default 6) |
| `CLOUD_DB_HOST` | the database host new blogs use (optional, default `localhost`) |

Setting it up with Plesk as the name server:

1. Point the domain's name servers at Plesk and add it as a subscription of the reseller (e.g.
   `ootd`). Blogs' A records are added to that subscription's DNS zone.
2. Give the reseller room: **Resellers → ootd → Change Plan / Customize**: subscriptions and
   customers unlimited (or your cap), and the permissions **Customer management**, **Subscription
   management** (create subscriptions) and **DNS zone management**.
3. As the reseller, **Service Plans → Add Plan** "OOTD Blog": e.g. 2 GB disk, 1 database, no mail
   accounts, DNS on, PHP 8.3 (FPM), SSL on.
4. On that plan, **Additional Services → Keep websites secured** (SSL It!), so each new blog gets a
   Let's Encrypt certificate. The owner is emailed once it has one. Through the XML API this is
   `<service-plan><add-plan-item><filter><name>OOTD Blog</name></filter><plan-item><name>urn:ext:sslit:plan-item-sdk:keep-secured</name></plan-item></add-plan-item></service-plan>`.
5. Make an API key for the reseller on the server, limited to this blog's IP:
   `plesk bin secret_key --create -login ootd -ip-address <this server's IP> -description "OOTD cloud blogs"`.
   A key for the admin would work too, but could then touch every site on the server.
6. Put the keys in `.env`, then check the login page shows the button. The cron job or worker must
   be running: it builds the blogs and wakes them every minute.

With the domain at Cloudflare instead, skip step 4 and add a token that can only edit DNS in its
zone (**My Profile → API Tokens → Edit zone DNS**); records are then proxied and https comes from
Cloudflare (SSL mode Full or Flexible).

Admins see every blog made from here in `/admin` → **Cloud blogs**, where a failed build can be
retried and a blog suspended, resumed or deleted.

## Your users' data

Every account can download its data from `/account` → **Your data**: a zip of JSON files and
photos. That covers GDPR access and portability requests.
