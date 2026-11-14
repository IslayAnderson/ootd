# OOTD federation roadmap

What it would take to turn OOTD from one closed site into software anyone can run, where every
instance (self-hosted or hosted by us in the cloud) talks to the others.

Written against `claude/development` at 0.6.0. Nothing here is built yet. Release numbers follow
the existing `major.feature.minor` scheme and continue from [ROADMAP.md](ROADMAP.md).

## The goal

- **One codebase, two ways to run it.** Self-hosted: `docker compose up` on your own server.
  Cloud: we run instances for people who don't want a server. Both are the same software, and an
  account can move between them.
- **Instances federate.** Someone on `ootd.example` can follow someone on `wardrobe.club`, see their
  outfits in their feed. Wear stats stay private to each person's own instance.
- **Talk to the wider Fediverse.** Use ActivityPub, the protocol Mastodon and Pixelfed speak, so an
  OOTD outfit shows up as a photo post on Mastodon, and Mastodon users can follow OOTD users. OOTD
  instances additionally exchange the richer data (garments, brands) that other software ignores.

## Where OOTD is today

What the code assumes, and why each assumption blocks federation:

| Today | Why it blocks federation |
| --- | --- |
| **Everything is behind login.** `init.php` redirects guests away from every page except login/signup/invite. | Remote servers are guests. They must be able to fetch profiles and posts. |
| **No idea of its own address.** No base URL in `.env` or settings; links are all relative. | Every federated object needs a permanent absolute URL, such as `https://host/outfit/<id>`. |
| **One global feed.** `/feed` is "the last 30 outfits by anyone". No follows. | Federation is built on follows: who you follow decides whose posts are sent to you. |
| **No visibility setting.** Every post is visible to every member. | Posts need public / followers-only / private, since public now means the whole internet. |
| **Images live in `MEDIUMBLOB` columns** and are base64-inlined into each page. | Remote servers need image URLs to fetch. Also slow and heavy as data grows (already noted in the audit). |
| **Create only.** No edit or delete. | Federation needs `Update` and `Delete` sent out, and users need to be able to retract a public post. |
| **Everything happens inside the web request.** No queue, no cron. | Sending a post to hundreds of servers, with retries, has to happen in the background. |
| **Users are numbered rows** (`user_id` int); `garments.user_id`, `outfits.user`, `blogs.user` point at them. | Remote people have no local row. Need an "actor" that can be local or remote. |
| **Brands, categories and tags are one shared local list.** | Each instance has its own list; names need matching across instances. |
| **Installed by a web form** writing `.env`; SCSS compiled at runtime; `/vendor` web-reachable (F12); Google Fonts/cdnjs (F15). | Self-hosting must be reproducible, non-interactive and secure by default. |
| **Invite-only, admin-approved signup.** | Fine to keep, but instances need their own registration policy setting and rules. |
| **No tests.** | Signatures and protocol parsing are exactly where silent breakage happens. |

Things that already help: posts have random public ids (`outfit_id`, `garment_id`, `blog_id`);
a versioned schema and upgrade step exist; settings live in a table with an admin UI; sessions,
CSRF and invites are already hardened.

## Key decisions (to confirm before starting)

Work is going ahead on the recommended options below; every assumption made along the way is
logged in [DECISIONS.md](DECISIONS.md) for sign-off.

1. **Protocol: ActivityPub.** It's what Mastodon, Pixelfed and the rest use, so OOTD users get an
   audience on day one. Bluesky's AT Protocol is the alternative but has a different model
   (personal data repositories, relays) and much less PHP tooling.
2. **What federates.** Outfits (as photo posts), blogs (as articles), profiles and follows. Garments
   federate between OOTD instances. **Wears and insights never leave the instance**: they're
   personal habit data, and the privacy page says so.
3. **Default visibility.** Today's posts were made inside a closed network. Recommended: existing
   posts become "followers only" on upgrade, and new posts default to whatever the user picks.
   Nothing old becomes public without the owner choosing it.
4. **Cloud model: one instance per customer**, not one shared multi-tenant database. Each cloud
   customer gets their own container and database, running the exact self-host image. This keeps
   cloud and self-host identical, makes moving between them a database export, and isolates
   tenants. It costs more per tenant, which is acceptable at this scale. (Alternative: a
   `tenant_id` column on every table. Cheaper to run, but every query, cache and file path must
   be tenant-aware forever, and self-host and cloud drift apart.)
5. **Library or hand-rolled.** The ActivityPub core (signatures, JSON-LD, WebFinger) is small
   enough to write in the house style and test thoroughly; a PHP library (e.g. `landrok/activitypub`)
   is worth a short spike first to compare. Decide in 0.10.0.
   **Spike result (0.10.0): hand-rolled.** What 0.11.0–0.12.0 need is WebFinger, actor and object
   JSON, HTTP Signatures (RSA-SHA256 over a few headers, which `openssl_sign` does in a few lines)
   and an inbox. `landrok/activitypub` covers more than that but brings its own HTTP client and
   type system, and still leaves delivery, retries and storage to the app. OOTD already has the parts it lacks (jobs with back-off from
   0.9.0, media, visibility), so the library would mostly duplicate them. Hand-rolled code will be
   tested against the published HTTP Signature examples and against a real Mastodon instance
   before 0.11.0 ships.
6. **Licence.** The repo has no licence file. Self-hostable, federated software normally ships
   with one; AGPL-3.0 is common in this space (it keeps hosted forks open). Needs the owner's call
   before anyone else is invited to run it.

## Releases

Grouped into four stages. Each stage is useful on its own, and the first two improve the
single-site app even if federation never ships.

### Stage 1: foundations (0.7.0 – 0.9.0)

#### 0.7.0 — Addresses, visibility and public pages

- `APP_URL` in `.env` (asked for by the installer; upgrade step derives it from the current host)
  and an `absolute_url()` helper.
- `visibility` column on outfits, garments and blogs: `public`, `followers`, `private`. Picker on
  the "New post" forms. Upgrade sets existing rows to `followers` (decision 3).
- Guest-readable routes: `/@username` profile, `/outfit/<id>`, `/garment/<id>`, `/user/<u>/blog/<id>`
  render **public** items to logged-out visitors; everything else still redirects to login.
  `$guest_paths` becomes a rule check, not a fixed list.
- Permalinks on every post (each outfit currently has no page of its own).
- Edit and delete for outfits, garments and blogs (soft delete with `deleted_at`, so a `Delete`
  can be federated later).
- Fix the PDO DSN (`;mysql:charset=utf8mb4;` is ignored; should be `;charset=utf8mb4;`) and
  make sure every table is `utf8mb4`, since federated text will include any language and emoji.

#### 0.8.0 — Media

- `media` table (the one in the original design diagram): `media_id`, owner, mime, width,
  height, bytes, `alt_text`, storage key, `remote_url` (for later), `blurhash` optional.
- Storage adapter interface with two drivers: local disk (`storage/` outside the web root,
  served by a `/media/<id>` route that checks visibility) and S3-compatible (for cloud and for
  self-hosters who want it).
- Resize on upload (thumbnail + display size), strip EXIF/GPS (phone photos carry location).
- Alt text field on uploads (needed for accessibility, and Mastodon shows it).
- Migration moves every existing `BLOB` into `media` and drops the columns in a later schema.
  `image_bin_encode()` is replaced by `media_url()`.

#### 0.9.0 — Follows, timelines and background jobs

- `follows` table (follower, followed, `state` pending/accepted) and follow/unfollow on
  profiles. Optional "approve followers" per account.
- `/feed` splits into **Following** (people you follow) and **Local** (everyone public on this
  instance, i.e. today's feed). **Federated** is added in 0.11.0.
- `jobs` table plus `bin/worker.php` (long-running CLI) and `bin/cron.php` (for hosts with only
  cron). Retries with back-off, failure log visible in `/admin`.
- Notifications table (new follower, later: likes and replies from elsewhere).
- First PHPUnit suite: routing, visibility rules, follows. CI on GitHub Actions.

### Stage 2: self-hostable (0.10.0)

#### 0.10.0 — Packaging and instance settings

- **Docker image** (PHP-FPM + nginx or Apache) with CSS compiled at build time, not per request.
  `docker-compose.yml` with MySQL, a worker container and a volume for media.
- **CLI installer and migrations**: `bin/ootd install`, `bin/ootd migrate`, `bin/ootd user:create`,
  all driven by environment variables. The web installer stays as a fallback for shared hosting.
- Locked-down web root: only `index.php`, `Assets/` and the needed TinyMCE files are served
  (closes F12). Self-hosted fonts and Chartist (closes F15).
- **Instance settings**: name, description, rules, admin contact, registration mode
  (closed / invite / approval / open), default visibility.
- **Email** (SMTP via `.env`): invites, password reset (doesn't exist today), notifications.
- **Account export**: a zip of your profile, posts, garments, wears and media as JSON + files.
  Needed for GDPR anyway, and it's the basis of moving between instances.
- Health check endpoint, documented backup/restore, upgrade guide, `docs/SELF-HOSTING.md`.
- Federation spike: compare a library with hand-rolled signatures (decision 5).

### Stage 3: federation (0.11.0 – 1.0.0)

#### 0.11.0 — Discoverable and followable (outbound)

The point where a Mastodon user can find and follow an OOTD user.

- `/.well-known/webfinger`, `/.well-known/nodeinfo` + `/nodeinfo/2.1`, `/.well-known/host-meta`.
- A keypair per user (RSA, stored encrypted) and an instance actor.
- Content negotiation on existing URLs: `Accept: application/activity+json` on `/@username`
  returns a `Person`; on `/outfit/<id>` returns a `Note` with the photo as an `Image` attachment,
  the caption as content and tags as `Hashtag`s.
- `outbox`, `followers`, `following` collections (paged).
- `inbox` that accepts `Follow` / `Undo Follow` and replies `Accept`.
- HTTP Signatures (draft-cavage, which Mastodon still requires) on outgoing requests and
  verification on incoming ones; digest checking; clock-skew limit.
- On post / edit / delete of a public or followers-only item: queue `Create` / `Update` /
  `Delete` to every follower's inbox (shared inbox where offered).
- `remote_actors` table caching fetched profiles and keys.

#### 0.12.0 — Following out (inbound)

The point where OOTD users can follow people elsewhere and see their posts.

- Search by handle (`@name@host`) → WebFinger → follow.
- Inbox handles `Create`, `Update`, `Delete`, `Announce`, `Like`, `Undo`, `Accept`/`Reject`,
  and `Delete` of an actor (account gone).
- Remote posts stored with `origin` (local/remote), `ap_id` (canonical URL) and `actor_id`.
  Garments, outfits and blogs gain these columns rather than separate remote tables, so the
  existing render functions work on both.
- Remote media is fetched by the worker, re-encoded and cached through the 0.8.0 storage
  adapter (never hot-linked, never fetched in the page request), with a cache size limit.
- **Federated** timeline tab; remote profiles at `/@name@host`.
- **SSRF protection** on every outgoing fetch: block private/loopback IP ranges, limit
  redirects and response size, set timeouts.

#### 0.13.0 — OOTD-to-OOTD extras

Content that only other OOTD instances understand, layered on top so Mastodon still sees a
normal photo post.

- JSON-LD context at `/ns` defining `ootd:Garment`, `ootd:brand`, `ootd:category`,
  `ootd:garments` (an outfit's list of garments).
- Outfits link their garments; a receiving OOTD instance renders the garment strip and can show
  the remote garment page. A wardrobe is published as a `Collection` (public garments only).
- Blogs federate as `Article` with their linked outfits.
- Brand/category matching: incoming names are matched to the local list case-insensitively,
  unknown ones go to the existing moderation queue (`in_moderation`) instead of being created
  silently.
- Interactions: Like on outfits ("love this look") and replies as comments, with notifications.

#### 0.14.0 — Moderation and safety

Has to land before any instance is opened to the public, even if it means delaying 1.0.0.

- Report (`Flag` activity, received and sent), admin report queue.
- Block and mute (user level), domain block / silence / suspend (instance level), with an
  import for shared blocklists.
- Content warnings and "sensitive media" flag, both directions.
- Rate limits on login, signup, posting and inbox; inbox size limits.
- Privacy page and in-app copy rewritten: public posts can be copied by other servers and
  deletion elsewhere can't be guaranteed.

#### 1.0.0 — Stable federation

- Interop tested against Mastodon, Pixelfed and GoToSocial (and two OOTD instances); test
  fixtures captured from each.
- Account migration: the `Move` activity and `alsoKnownAs`, plus import of the 0.10.0 export, so
  an account moves between cloud and self-host with followers carried across.
- Key rotation, retry/backoff tuning, delivery stats in `/admin`.
- Public docs: protocol notes (`docs/FEDERATION-PROTOCOL.md`) for other developers.

### Stage 4: cloud (1.1.0 – 1.2.0)

Runs the same image as self-host (decision 4). Mostly infrastructure rather than app code; the
app only needs to be configurable entirely by environment and to report its own health.

#### 1.1.0 — Managed instances

- Control plane (separate small service): sign up, choose a subdomain, provision a container +
  database + storage bucket, run `bin/ootd install`.
- Custom domains with automatic TLS. A domain must be fixed before federating (ActivityPub ids
  can't change), so the UI makes that clear up front.
- Rolling upgrades: new image → `bin/ootd migrate` → health check → switch.
- Backups, monitoring, log shipping, per-tenant usage (storage, members).
- Import of a self-hosted export into a cloud instance, and the reverse.

#### 1.2.0 — Hosted for everyone

- A flagship instance people can join without running anything.
- Billing for custom-domain / larger instances, if wanted.
- Terms of service, abuse contact, data-processing terms, and the operational side of
  moderating a public instance.

## Data model changes at a glance

| Release | Change |
| --- | --- |
| 0.7.0 | `visibility`, `updated_at`, `deleted_at` on outfits/garments/blogs; `APP_URL`; utf8mb4 everywhere |
| 0.8.0 | `media` table; image columns migrated then dropped |
| 0.9.0 | `follows`, `jobs`, `notifications` |
| 0.10.0 | instance settings rows; `password_resets` |
| 0.11.0 | `users.public_key`, `users.private_key` (encrypted); `remote_actors`; `deliveries` |
| 0.12.0 | `origin`, `ap_id`, `actor_id` on outfits/garments/blogs; `follows` points at actors |
| 0.13.0 | `likes`, `replies`; brand/category alias tables |
| 0.14.0 | `reports`, `blocks`, `domain_blocks`; `sensitive`, `content_warning` on posts |
| 1.0.0 | `users.also_known_as`, `users.moved_to` |

Every change goes through the existing `$schema_version` / `$migrations` upgrade path, so a
0.6.0 install upgrades step by step.

## Risks

- **Interop quirks.** Each Fediverse server reads ActivityPub a little differently. Budget time in
  every federation release for testing against real servers, not just the spec.
- **PHP request model.** Inbox traffic arrives in bursts. Keep the inbox handler tiny (verify
  signature, store, queue) and do the work in the worker.
- **Privacy expectations.** Users joined a closed, invite-only site. Making anything public must be
  an explicit choice per post, and the upgrade must not expose old posts (decision 3).
- **Moderation load.** Federating means receiving content from servers you don't control. Stage 3
  can't be opened to the public without 0.14.0.
- **Deletion isn't guaranteed.** Other servers may keep copies. Needs to be said plainly in the UI
  and privacy page.
- **Scope.** This is roughly as much work again as everything built so far, multiplied by the
  testing burden of talking to other software. Stages 1 and 2 are worth doing first and on their
  own; they make OOTD better as a single site and are prerequisites for everything else.
