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
