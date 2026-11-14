# OOTD roadmap

There is no written roadmap in the repo, so this one is assembled from what the project already
points at: navigation links to pages that don't exist yet, half-built forms, mock-up partials,
`todo` comments and the intended schema in the `documents` branch. Finding numbers (S1, I3, …)
refer to [AUDIT.md](AUDIT.md).

## Versioning

`major.feature.minor`

- **major** — 0 until a public launch.
- **feature** — bumped for each release that adds user-facing features.
- **minor** — fixes on top of a feature release, no new features.

The code base as found is treated as **0.1.0** (`User` is `@since 0.1`).

## Branches (Claude's working set)

All of Claude's work lives under `claude/`, separate from `main`:

| Branch | Purpose |
| --- | --- |
| `claude/main` | **Staging.** Deployed to the owner's Plesk staging server. Receives `claude/development` with `--no-ff` once a release has passed local testing. |
| `claude/development` | Integration branch. Every release branch is merged here with `--no-ff`. |
| `claude/release/<major.feature.minor>` | One branch per release, cut from `claude/development`, containing that release's work. |

Each release branch builds on the previous one, so `claude/development` always holds the latest
release. `claude/main` is promoted from `claude/development` and runs on staging; nothing is merged
into `main`. After a promotion, an admin runs the upgrade at `/install.php` on staging.

### Staging

`https://ootd.islayanderson.co.uk`, on the owner's Plesk server (PHP 8.3, MariaDB 10.6), runs
`claude/main`. Its database is test data. Each finished release is pushed to GitHub (`claude/main`,
`claude/development`) and reaches the server like this:

1. Rehearse: restore the latest staging backup into a scratch database on the server and run
   `bin/migrate.php` against a copy of the code there, twice (the second run must change nothing).
2. Back up `.env`, the database and the site folder into `/root/ootd-backups`.
3. Copy the `claude/main` tree (`git archive`) over the site folder **without deleting**: the
   server has files that aren't in the repo (DECISIONS Q6). `.env`, `tmp/`, `vendor/` and
   `storage/` are never overwritten. Files stay owned by the subscription user, and the site
   folder itself keeps the `psaserv` group.
4. Run `composer install --no-dev` and `php bin/migrate.php` as the subscription user (vendor is
   backed up first), and check `/health`, the public pages and the error log.
5. Refresh Plesk's copy of the repository (`plesk ext git --fetch`). Plesk's Git integration
   deploys `claude/main` automatically, so a stale copy there can overwrite the site with an
   old commit (DECISIONS Q11).

| Release | Status |
| --- | --- |
| 0.1.1 | Done, merged to `claude/development` |
| 0.2.0 | Done, merged to `claude/development` |
| 0.3.0 | Done, merged to `claude/development` |
| 0.4.0 | Done, merged to `claude/development` |
| 0.5.0 | Done, merged to `claude/development` |
| 0.6.0 | Done, merged to `claude/development` |
| 0.6.1 | Done, merged to `claude/development` |
| 0.7.0 | Done, merged to `claude/development` |
| 0.7.1 | Done, merged to `claude/development` |
| 0.8.0 | Done, merged to `claude/development` |
| 0.9.0 | Done, merged to `claude/development` |
| 0.10.0 | Done, merged to `claude/development` |
| 0.11.0 | Done, merged to `claude/development` |
| 0.11.1 | Hotfix, merged to `claude/development` |
| 0.12.0 | Done, merged to `claude/development` |
| 0.12.1 | Done, merged to `claude/development` |

## Upgrading an existing install

1. Deploy the code and run `composer install`.
2. Log in as the admin (the original installer's admin is permission `0`; the upgrade accepts it).
3. Open `/install.php` and press **Upgrade database**. This adds the missing columns and the
   `blogs`, `invites` and `wears` tables, remaps permission ids and moves `schema_version` to 2.
   It is safe to run more than once.
4. Everyone, including you, is logged out once: sessions are now checked against
   `users.session` (AUDIT F4). Log back in.
5. In `/admin` → Settings, turn **Recompile sass** off once the CSS is built.
6. Change the admin password from `/account` if it is still `root`.

## Releases

### 0.1.1 — Security, install and schema fixes ✅

Make a fresh install actually work and close the anonymous-visitor holes.

- Stop leaking `.env` on the 404 page (S1); `exit` after the guest redirect (S2).
- Lock the installer once installed (S3); installer password field is a password field (S11).
- Admin-only `/admin` (S4); line permission ids up with the `permissions` table (S5).
- `escape()` output helper (S7); fix CSRF expiry and the "one bad token logs everyone out" path (S8, S9); don't pre-fill the admin email (S10).
- Bring the schema up to date: `garments`, `outfits`, `users.image`, new `blogs` table (I1–I4).
- `schema_version` setting plus an upgrade step so an existing install can be brought forward by re-running the installer while logged in as admin (I5).
- Docroot-relative `require` in `index.php` (I6); README setup notes (I7).
- Logout, empty result sets, `User` setters, `Post\Garment` filter, page titles (B1, B2, B3, B5, B6).

### 0.2.0 — Posting ✅

The `/account` page has *New blog / New outfit / New garment* tabs that don't submit.

- `API/garment/create.php`, `API/outfit/create.php`, `API/blog/create.php` (CSRF-checked, image upload to the existing `BLOB` columns).
- Working tabs and forms on `/account`; garment form uses the seeded `brands` and `categories`.
- Avatar upload (`API/user/avatar.php`).
- Blogs render on profiles (B4) and get a page at `/user/<username>/blog/<blog_id>`.

### 0.3.0 — Wardrobe and garments ✅

- `/wardrobe` shows the signed-in user's real garments instead of dummy data (B8).
- Category, tag and search filters (the filter bar already exists).
- `/garment/<garment_id>` detail page (already linked from outfits and wardrobe).
- `/outfits` lists the signed-in user's outfits.

### 0.4.0 — Admin and invites ✅

- Live user table: list users, reset a user's session, change permission, kill all sessions (B9).
- Settings read from and saved to the `settings` table, with the seeded `installed` / `schema_version` hidden.
- Invite flow: `/signup` → `API/user/invite.php` stores a request; admins approve it in the
  Moderation section, which generates an invite code; `/invite?code=…` → `API/user/create.php`
  only accepts a valid unused code (S6). Fix `Validate::basic()`.
- Change password on `/account` (S10).

### 0.5.0 — Insights ✅

Replaces the hard-coded "just for demo" stats block (B7).

- `wears` table: "Wore this today" on an outfit logs a wear for the outfit and each garment in it.
- `/insights` (already in the nav): wears per month chart (Chartist, already used), most-worn
  garments, and "you haven't worn X in N months" for garments with no recent wear.

### 0.6.0 — Finished UI ✅

Restyle with design cues from Glamcult magazine: editorial, monochrome and type-led.

- Tokens and mixins in `variables.scss`: ink/paper palette, `display()` (Newsreader serif, upper
  case, italic `<em>` accents), `kicker()` (Inter Tight small caps), `band()` full-bleed sections
  on a centred column, `inverted` black bands.
- New partials `typography.scss`, `forms.scss` (underline inputs, black block buttons) and
  `page/insights.scss`; every page restyled, with empty states and error pages given the same voice.
- Full-screen black menu that only shows links you can use (Admin for admins, Log in for guests).
- Feed alternates white and black bands; on desktop each outfit becomes a two-column spread.
- Dates shown as "14 November 2026" via `date_display()`.
- Privacy page written.
- Fixed outfits showing the newest garment's timestamp instead of their own (F14).

### 0.6.1 — Wordmark ✅

- Top bar shows the OOTD wordmark on its own; the flower icon is dropped from `logo.svg`
  (the favicon is unchanged).

### 0.7.0 — Addresses, visibility and public pages ✅

First release of the [federation roadmap](FEDERATION.md). Upgrade with `/install.php` (schema 3).

- `APP_URL` in `.env`: asked for by the installer, added from the admin's address on upgrade.
  `app_url()` / `absolute_url()` helpers.
- Every garment, outfit and blog has a visibility: **Public** (anyone, signed in or not),
  **Members** (anyone signed in here, which is how everything behaved before) or **Only me**.
  Existing posts become Members. Picker on every "New post" form and edit form.
- Guests can open `/user/<username>`, `/outfit/<id>`, `/garment/<id>` and blog pages, and see
  only what's public. Real names and wear history are never shown to guests. `/@username`
  redirects to the profile. `/privacy` is open to guests.
- Outfit permalinks (`/outfit/<id>`, linked from each post's date).
- Edit and delete for outfits, garments and blogs, owner only, from each item's page. Deletes are
  soft (`deleted_at`) so they can be federated later. Photos can't be changed yet (0.8.0).
- Connection charset fixed and every table converted to `utf8mb4` (AUDIT F16).

### 0.7.1 — Upgrade fixes found on staging ✅

Found by rehearsing the upgrade on a copy of the staging database (MariaDB 10.6, created by an
early version of the app). Schema 4.

- `php bin/migrate.php [--app-url=…]` runs the database upgrade from the command line, for
  servers where nobody can sign in to `/install.php` (staging, deploy scripts). Pulled forward
  from 0.10.0.
- The schema version is saved after each step, so re-running after a failure can't repeat a
  step (re-running schema 1 used to demote every admin).
- Works on MariaDB: the utf8mb4 conversion no longer names a MySQL-only collation.
- Older databases: adds the missing `users.csrf` column and `blogs.tags`; widens early blog
  columns (content was capped at 4096 characters); stops `ON UPDATE CURRENT_TIMESTAMP` moving a
  post's date when it's edited; keeps an early-design `invites` table as `invites_legacy` and
  creates the current one.
- `.env` values with `=`, `;`, `!` or quotes (real passwords) load correctly.

### 0.8.0 — Media store ✅

Second release of the [federation roadmap](FEDERATION.md). Schema 5: the upgrade moves every
existing image into the store (`storage/` must be writable by PHP).

- `media` table and a `Storage` interface with two drivers: local disk (`storage/`, or
  `STORAGE_PATH`) and S3-compatible (`MEDIA_DRIVER=s3` plus `S3_*` settings, signed with AWS
  Signature V4; checked against AWS's published example, not yet against a live bucket).
- Each upload is re-encoded into a 1600px display size and a 480px thumbnail. Re-encoding
  removes EXIF, including GPS location; the EXIF rotation is applied first so phone photos stay
  upright. Photos become JPEG; images with transparency (cut-out garments) stay PNG.
- Images are served from `/media/<id>/<display|thumb>`, which checks the visibility of the post
  using them: public posts' images are cacheable by anyone, others only privately. Avatars are
  always visible. `storage/` itself is closed to the web.
- "Describe the photo" (alt text) on every upload, editable later; used as the image's `alt`.
- Outfit and garment photos can be replaced from their edit forms; the old files are deleted.
- Pages no longer inline images as base64, so they're much smaller.
- The old `image` BLOB columns are left in place, unused, so a rollback still has its images.

### 0.9.0 — Follows, timelines and background jobs ✅

Third release of the [federation roadmap](FEDERATION.md). Schema 6.

- Follow and unfollow from a profile, which now shows follower and following counts. Follows
  are accepted straight away unless the account turns on "Approve new followers" (Account →
  Followers), where requests are accepted or declined.
- New **Followers** visibility: only the owner's accepted followers see the post.
- `/notifications`: new follower, follow request, request accepted. The menu shows the unread
  count.
- `/feed` has two tabs: **Following** (you and the people you follow) and **Local** (everything on
  this instance you can see, the old feed). Following is the default once you follow someone.
- Background jobs: a `jobs` table, `bin/worker.php` (long-running) and `bin/cron.php` (run every
  minute from cron). Failed jobs retry with back-off (1 min doubling, 8 tries); failures and a
  "Retry failed jobs" button are under `/admin` → Jobs.
- The first job, `media_purge` (daily), deletes the photos of posts deleted more than 30 days ago
  and uploads never attached to a post.
- PHPUnit suite (`vendor/bin/phpunit`: visibility rules, guest routes, URLs, `.env` parsing, the
  S3 signature) and GitHub Actions CI (lint and tests on PHP 8.3 and 8.4).

Run the jobs with one of:

```
* * * * * php /path/to/ootd/bin/cron.php
php bin/worker.php            # under systemd or supervisor
```

### 0.10.0 — Self-hostable ✅

Fourth release of the [federation roadmap](FEDERATION.md). Schema 7. Self-hosting is documented in
[SELF-HOSTING.md](SELF-HOSTING.md).

- **Instance settings** in `/admin`: name (used in titles and the footer), description, rules,
  contact, who can join (closed / invite / open) and the default visibility of new posts. A public
  `/about` page shows them.
- **Open registration**: with Registration set to open, `/signup` creates an account directly.
- **Email** over SMTP (`MAIL_*` in `.env`), sent from the job queue. Approving an invite now
  emails the signup link. Without SMTP, mail goes to `tmp/mail.log`.
- **Forgot password**: `/forgot` emails a one-hour, single-use link; resetting signs the account
  out everywhere.
- **Download your data** (`/account`): a zip of profile, wardrobe, outfits, blogs, wears, follows
  and photos as JSON plus image files.
- **`bin/ootd`**: `install` (from environment variables), `migrate`, `user:create`, `jobs`,
  `health`, `version`.
- **`/health`** for uptime monitors and container health checks.
- **Docker**: `Dockerfile`, `docker-compose.yml` (web, worker, MariaDB) and `.env.docker.example`.
  CI builds the image, starts the stack and checks it.
- Locked-down web root: `.htaccess` blocks code, config, data and `vendor/` (except TinyMCE)
  (AUDIT F12).
- Dependency updates for security advisories (AUDIT F26), class-loader warnings removed (F25),
  installer warnings on empty databases fixed (F24).
- The old image BLOB columns are dropped (D27).
- ActivityPub will be hand-rolled (FEDERATION decision 5).

### 0.11.0 — Followable from the Fediverse ✅

Fifth release of the [federation roadmap](FEDERATION.md). Schema 8. Someone on Mastodon (or any
ActivityPub server) can now find an OOTD account by its handle, follow it, and see its posts.

- **Handles**: every profile shows `@username@host`. `/.well-known/webfinger`, `host-meta` and
  NodeInfo 2.1 make accounts and the server discoverable.
- **Actors**: `/user/<name>` answers ActivityStreams JSON (a `Person`) when asked for it, with a
  public key; an instance actor (`/actor`) signs the server's own requests. Keys are RSA-2048,
  made on first use; private keys are stored encrypted with `PWD_SEED`.
- **Posts**: public and followers-only outfits federate as `Note`s with the photo, alt text and
  hashtags; blog posts as `Article`s. Members and Only me posts never leave the server. The
  outbox lists public posts; follower and following collections give counts only.
- **Being followed**: the inbox accepts signed `Follow` and `Undo`, answers `Accept`, and
  honours "Approve new followers" (requests from other servers appear under Account → Followers).
  Notifications show the remote handle.
- **Delivery**: creating, editing or deleting a post queues `Create`, `Update` or `Delete` to every
  follower's server (shared inboxes once each), signed with HTTP Signatures. Changing a post to
  Members or Only me sends `Delete`; back to Public or Followers sends `Create`.
- **Safety**: incoming requests must be signed by the actor they claim to be, with a matching
  digest and a recent date. Outgoing requests go only to public https addresses, pinned to the
  checked IP, with timeouts and a 1 MB limit.
- Photos of followers-only posts get links with a key so followers' servers can fetch them.
- `/admin` → Settings → **Federation** turns all of this off.
- Federation requests skip sessions and page rendering.
- Tests: unit tests for signatures and address checks, and an end-to-end test against a fake
  remote server that checks OOTD's signatures independently.

### 0.11.1 — Stale styles fix ✅

- Stylesheet and script links carry the file's modification time (`main.css?v=…`). Cloudflare
  in front of staging caches `/Assets` for a year, so after a deploy it kept serving the old
  stylesheet; a new version is now a new address.

### 0.12.0 — Following out ✅

Sixth release of the [federation roadmap](FEDERATION.md). Schema 9. Members can now follow people
on Mastodon and other Fediverse servers and see their posts.

- **Finding people**: a search box on the feed (and `/search`) takes a username here, a handle
  (`@name@mastodon.social`, looked up with WebFinger) or the address of a profile elsewhere.
  Remote profiles live at `/@name@host`, for signed-in members only, with a Follow button.
- **Following**: sends `Follow`, shows Requested until their server sends `Accept` (or `Reject`),
  then fetches their 20 latest public posts from their outbox. Unfollowing sends `Undo`.
  Account → Followers lists everyone followed elsewhere; following counts include them.
- **Their posts**: the inbox stores `Create` and `Update` of notes and articles from people
  someone here follows, and honours `Delete` (of a post, or of their whole account). Posts must be
  by the sender and live on the sender's server. Replies and direct messages aren't stored.
  Content is sanitised when shown; content warnings and sensitive media start folded.
- **Feeds**: Following mixes posts from people you follow here and elsewhere; a new
  **Federated** tab shows everything from other servers. Remote posts are for members only:
  public ones to every member, followers-only ones to members who follow that person.
- **Photos** are fetched by the worker (up to 16 MB, 50 megapixels), re-encoded into the media
  store and served through `/media` like local photos, never hot-linked. A daily job drops the
  oldest when they take more than `remote_media_mb` (Settings, default 1024 MB); the post then
  links to the photo on its own server. Posts and photos are deleted when nobody here follows
  that person any more.
- **Likes and boosts** of posts here, from other servers, become notifications linking to the post.
- Self-hosting: fonts and Chartist are served from `Assets/` (AUDIT F15); `LICENSE` (AGPL-3.0);
  Cloudflare R2 works with just its endpoint, and `php bin/ootd storage:check` tests a bucket.
- A background job that can never succeed (email to a malformed address) fails once instead of
  retrying for days.
- Tests: an end-to-end run against a simulated Mastodon-style server (61 checks: search,
  follow, Accept/Reject, backfill, posts, spoofing, likes, deletes, cache limit, unfollow).

### 0.12.1 — Email without SMTP ✅

- Email goes through PHP's `mail()` (the server's own sendmail) when no SMTP server is set, so
  password resets and invites work on ordinary PHP hosts with no setup. `MAIL_DRIVER` (`smtp`,
  `mail`, `log`) picks explicitly; the sender defaults to `noreply@` the site's domain (Q9).
- Staging: the unused `profile`, `profile_options` and `size_*` tables and `garments.size` were
  dropped after a backup (Q3).

## Later (not started)

Federation and self-hosting (releases 0.7.0 onwards) are planned in [FEDERATION.md](FEDERATION.md).


- Block `/vendor` from the web except TinyMCE (AUDIT F12).
- Edit and delete for garments, outfits and blogs (only create exists).
- Email the invite link instead of an admin copying it from `/admin`.
- `media` table from the design diagram instead of inline `BLOB`s (and images served by URL).
- Moderation queue for user-submitted brands, categories and tags (`in_moderation` columns exist).
- Only recompile SCSS when the admin "Re-compile SASS" setting is on.
- PHPUnit tests (already a dev dependency).
- Self-host the Google Fonts (Newsreader, Inter Tight) if the site should make no third-party requests (AUDIT F15).
