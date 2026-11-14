# OOTD codebase audit

Audit of `main` at `fc0b7c8` ("Fix SQL injection in User::get_password()"), taken on 2026-11-14
before any `claude/*` work. Every finding below was reproduced against a fresh install
(PHP 8.4, MySQL 9.4, PHP built-in server with a front-controller router) unless marked
*(read)*, meaning it was found by reading the code only.

Each finding lists the release on the `claude/*` branches that addresses it (see
[ROADMAP.md](ROADMAP.md)). "—" means it is documented but deliberately left alone.

## Project shape

| Area | Where | Notes |
| --- | --- | --- |
| Front controller | `index.php` | `switch` on the request path, `require`s a file in `Pages/`, wrapped in `partials/head.php` / `navigation.php` / `foot.php`. `.htaccess` rewrites everything that isn't a file to it. |
| Bootstrap | `init.php` | Session (files in `/tmp`), autoloads `Functions/*`, `Classes/*/*`, `partials/components/*`, loads `.env` via phpdotenv, generates CSRF token, **compiles SCSS on every request**, redirects guests to `/login`. |
| API | `API/<area>/<action>.php` | Hit directly (real files, so not rewritten). Each one `require`s `init.php`. Responds with a redirect or `json_encode(array("state" => …))`. |
| Classes | `Classes/<area>/<Name>.php` | `Mysql` (PDO wrapper, `Fetch($sql, $params)` returns rows or `errorInfo()`), `User`, `Validate`, `Post\Outfit`, `Post\Garment`, `Post\Wardrobe`, `Post\Blog`, empty `ReadXML`/`WriteXML`. Post classes load in the constructor and print HTML in `render_*()` methods. |
| Functions | `Functions/*.php` | `id_gen()`, `image_bin_encode()`, CSRF helpers, title/description buffer helpers. |
| Styles | `Assets/stylesheets/sass` | `main.scss` imports partials; one partial per page under `page/`. BEM-ish (`accordion__section-header`). Compiled to `Assets/stylesheets/css/main.css` at runtime. |
| JS | `Assets/javascript/main.js` | Vanilla, arrow-function `let`s grouped under `//LISTENERS`, `//UI`, `//API CALLS`, `//LOAD` comments. |
| Install | `install.php` → `install_actions.php` | Writes `.env`, then one `*_check_table()` / `*_create_table()` / `*_count_rows()` / `*_import()` set per table, seeds brands, categories, permissions, settings and an `admin` / `root` user. |
| Design docs | `documents` branch | `OOTD-Database.svg` (intended schema) and an unfilled `OOTD-Classes.svg` template. |

### Conventions to keep

- `snake_case` for SQL, PHP functions, methods and array keys; `PascalCase` class names.
- Integer key `<thing>_num` plus public string `<thing>_id` (generated with `id_gen()`) — this
  is what the code uses; the `documents` diagram uses `<thing>_id` + `<thing>_id_public`
  instead. The code wins (it is newer and it is what runs).
- SQL is always parameterised with `:a`, `:b`, … placeholders.
- Empty result sets are detected with `$row[0] == '00000'` (that is `Mysql::Fetch()` returning
  `errorInfo()`).
- PHP docblocks with `@param` / `@return` / `@throws` on class methods.
- Pages use `<section>` / `<article>` and the shared `.btn`, `.filters`, `.accordion` styles.

## Findings

Severity: **Critical** = exploitable by an anonymous visitor; **High** = breaks a core flow
or exposes data to users; **Medium** = wrong behaviour; **Low** = cleanup.

### Security

| # | Sev | Finding | Fix |
| --- | --- | --- | --- |
| S1 | Critical | `Pages/404.php` does `print_r($_SERVER)` and `print_r($_SESSION)`. phpdotenv mirrors `.env` into `$_SERVER`, so **any visitor requesting an unknown URL sees `DB_USER`, `DB_PASS` and `PWD_SEED`.** Reproduced while logged out. | 0.1.1 |
| S2 | Critical | `init.php` sends `Location: /login` for guests but never `exit`s, so **every page (including `/admin`) renders in full for logged-out visitors** behind a 302. | 0.1.1 |
| S3 | Critical | `install_actions.php` has no "already installed" guard. Anyone can POST to it and overwrite `.env` (pointing the site at their own database or wiping the password pepper). | 0.1.1 |
| S4 | High | `/admin` has no permission check; any logged-in user (and, via S2, any visitor) gets it. | 0.1.1 |
| S5 | High | Permission ids don't line up: `permissions` is seeded `admin`=1, `moderator`=2, `user`=3, but `create_user()` stores `0` for the admin and `1` for normal users — so normal users hold the *admin* id. | 0.1.1 |
| S6 | High | `API/user/create.php` is open registration (no invite code, no CSRF) even though the UI only offers invite requests. `Validate::basic()` effectively never rejects anything (`gettype()` returns lowercase `"string"`/`"array"`, so it always hits `default`, and the result array is always truthy). | 0.4.0 |
| S7 | High | Output is never escaped — usernames, captions, garment names and blog text are echoed raw (stored XSS once posting exists). | 0.1.1 helper, used from 0.2.0 on |
| S8 | Medium | `CSRF_generate()` compares `time()` with `mktime($expiry)`; `mktime()`'s first argument is the *hour*, so the expiry check is meaningless. `API/csrf/static.php` is loaded as a hidden `<img>` on every page and does nothing. | 0.1.1 |
| S9 | Medium | `CSRF_validate()` calls `kill_all_sessions()` on a mismatch — one bad token logs **every user** out. | 0.1.1 |
| S10 | Medium | Default admin is `admin` / `root`, and `Pages/login.php` pre-fills the admin email. | 0.1.1 (pre-fill removed; password change is on the roadmap in 0.4.0) |
| S11 | Low | Installer form's password field is `type="text"`. | 0.1.1 |
| S12 | Low *(read)* | `brands_import()` and friends build `INSERT` strings by concatenation. Inputs are hard-coded today, so not exploitable, but it breaks on a brand containing `"`. | — |

### Install / schema (the installer is out of date)

The installer runs cleanly, but the tables it creates no longer match what the code queries.
On a fresh install: `/feed` dies with *Unknown column 'timestamp'*, `/account` with *Unknown
column 'user_id'*, `/user/<name>` with *Table 'ootd.blogs' doesn't exist*.

| # | Sev | Missing / wrong | Used by | Fix |
| --- | --- | --- | --- | --- |
| I1 | High | `garments`: no `garment_num`/`garment_id` split, no `user_id`, `timestamp`, `brand`, `category`. | `Post\Garment`, `Post\Wardrobe`, `Post\Outfit` | 0.1.1 |
| I2 | High | `outfits`: no `timestamp`. | feed, profile, account | 0.1.1 |
| I3 | High | `users`: no `image` (avatar). | `Post\Outfit` join | 0.1.1 |
| I4 | High | `blogs` table is never created. | `Post\Blog` | 0.1.1 |
| I5 | Medium | No way to upgrade an existing install — the installer only creates missing *tables*. | — | 0.1.1 (`schema_version` setting + upgrade step) |
| I6 | Medium | `index.php` does `require "./init.php"` (relative to the process cwd, not the docroot). Works under Apache, fatal under `php -S` or any server that doesn't chdir. | — | 0.1.1 |
| I7 | Low | README doesn't mention `PWD_SEED` (generated) or `TINYMCE` (used by `/account`), PHP ≥ 8.2 (`true` return type), or MySQL ≥ 8.0.4 (`JSON_TABLE`). | — | 0.1.1 |

### Bugs

| # | Sev | Finding | Fix |
| --- | --- | --- | --- |
| B1 | High | `User::deauthenticate()` runs two `UPDATE`s with no `;` between them → SQL syntax error, so logout throws. | 0.1.1 |
| B2 | High | Feed / profile loops do `foreach ($row as $outfit)` without the `'00000'` empty check, so an empty table renders warnings instead of an empty state. | 0.1.1 |
| B3 | Medium | `User::set_*()` use variable-variables (`$this->$user_id = …`), creating junk dynamic properties instead of setting the real ones; `authenticate()` reads `first_name`/`last_name` from the login request, which never has them. | 0.1.1 |
| B4 | Medium | `Pages/user.php` checks `if (!$blogs)` — inverted, and a constructed object is always truthy anyway, so blogs never render. | 0.2.0 |
| B5 | Medium | `Post\Garment` filters on `user_id` but reads `$garment->user`; `Post\Blog` / `Post\Garment` build the optional id filter by concatenating `$blog_id` into SQL without `AND`. | 0.1.1 |
| B6 | Medium | `Functions/meta.php`: `title_update()` / `decsription_update()` start an output buffer with a callback that expects two arguments (PHP passes buffer, phase), so titles are never replaced. | 0.1.1 |
| B7 | Low | `/account` and `/user/*` contain a hard-coded "just for demo" stats block reading `garment_num = 8`. | 0.5.0 (replaced by real insights) |
| B8 | Low | `/wardrobe` renders `partials/dummydata/wardrobe.php` six times; `/outfits`, `/privacy`, `/insights` are empty / unrouted; `/garment/<id>` links 404. | 0.3.0 / 0.5.0 |
| B9 | Low | `partials/user_table.php` and `partials/settings.php` are static mock-ups with real-looking personal data. | 0.4.0 |
| B10 | Low | `Pages/index.php` sends a redirect header then renders anyway. | — |

### Found while building 0.1.1 – 0.7.1

| # | Sev | Finding | Fix |
| --- | --- | --- | --- |
| F1 | High | The original installer never set the `installed` setting, so any existing site still reads as "not installed". The 0.1.1 installer lock therefore treats an existing `users` table as installed. | 0.1.1 |
| F2 | High | Existing installs store the admin as permission `0`, which the new admin check rejects; the upgrade step accepts a legacy `0` admin until schema 1 has run. | 0.1.1 |
| F3 | High | The CSRF token minted before login was never saved against the user, so the first form posted after logging in always failed (and logged the user out). | 0.2.0 |
| F4 | High | `users.session` was written on login but never checked, so logout-elsewhere, "Reset Session" and "Kill All Sessions" did nothing to an open browser. Sessions are now checked on every request. Side effect: **one active session per user** — logging in somewhere else ends the earlier session. | 0.4.0 |
| F5 | Medium | Images were `BLOB` (64KB max), too small for phone photos; now `MEDIUMBLOB`. | 0.1.1 |
| F6 | Medium | `Post\Outfit` used inner joins on its garment list, so an outfit with no linked garments failed to load. | 0.2.0 |
| F7 | Medium | `table.scss` gives tables `z-index: -1`, which put every button inside the admin tables behind the page (unclickable). | 0.4.0 |
| F8 | Medium | `/user.*/` route regex matched any path containing "user". | 0.3.0 |
| F9 | Low | `image_bin_encode()` built MIME types like `image/JPEG` from `finfo`'s text description. | 0.2.0 |
| F10 | Low | `users.session` was `varchar(32)`; widened to 128 in schema 2. | 0.4.0 |
| F11 | Low | `Functions/environment.php` warned during install because `.env` didn't exist yet. | 0.5.0 |
| F12 | Medium *(read)* | `/vendor` is inside the web root. `/account` relies on that to load TinyMCE, but it also exposes every Composer package's files. Worth blocking `/vendor` in `.htaccess` except `vendor/tinymce`. Now blocked, along with every code, config and data folder. | 0.10.0 |
| F13 | Low *(read)* | `Mysql::Fetch()` binds every value as `PDO::PARAM_STR` and then passes `$params` to `execute()` again; works, but the bind loop is redundant. | — |
| F14 | Medium | `Post\Outfit` selected `outfits.*` and `garments.*` together, so the garment's `timestamp` overwrote the outfit's and posts showed the wrong date. | 0.6.0 |
| F15 | Low *(read)* | Pages load Google Fonts, and insights loads Chartist from cdnjs: third-party requests, disclosed on `/privacy`. Self-hosting would remove them. | **Fixed 0.12.0**: fonts and Chartist are served from `Assets/` |
| F16 | Low *(read)* | `Mysql` builds its DSN with `;mysql:charset=utf8mb4;`, which PDO ignores, so the connection charset is whatever the server defaults to. Should be `;charset=utf8mb4;`. | 0.7.0 |
| F17 | Low | The garment page showed "Last worn" to every member, so anyone could see when someone else wore something. Wears are now only shown to their owner. | 0.7.0 |
| F18 | High | The upgrade saved `schema_version` only at the end, so re-running after a failed step repeated earlier steps; schema 1 remaps permissions, so a re-run demoted every admin. | 0.7.1 |
| F19 | High | Databases from early versions lack `users.csrf`, so every request after login failed once the upgrade had run. | 0.7.1 |
| F20 | Medium | MariaDB (and MySQL without `explicit_defaults_for_timestamp`) gave early tables `ON UPDATE CURRENT_TIMESTAMP`, so editing an outfit changed its date. | 0.7.1 |
| F21 | Medium | Early databases have a different `invites` table and smaller `blogs` columns (content capped at 4096 characters). | 0.7.1 |
| F22 | Medium | `Functions/environment.php` used `parse_ini_file()`, which fails on, or truncates, values containing `=`, `;`, `!` or quotes. | 0.7.1 |
| F23 | Low *(read)* | The staging database has tables no code uses: `profile`, `profile_options`, `sizes`, `size_category`, `size_sub_category`, `size_gender`, and `garments.size`. Left untouched; see DECISIONS Q3. | — |
| F24 | Low | The installer's older table checks read a missing array key on an empty database, so a fresh install printed PHP warnings (the result was still right). | 0.10.0 |
| F25 | Low | `Classes/classes.php` ran `eval("use X;")` for every class. A `use` inside `eval` does nothing, and each one raised a warning, which filled the command-line output. | 0.10.0 |
| F26 | Medium | `composer audit`: TinyMCE 7.6 had XSS advisories and PHPUnit 11.5.3 an unsafe-deserialisation one. Updated to TinyMCE 7.9.3 and PHPUnit 11.5.58. One TinyMCE advisory remains (media plugin, fixed in 8.9.3); OOTD doesn't load that plugin and sanitises rich text on the server, see DECISIONS D24. | 0.10.0 |
| F27 | Low | The CSRF token is stored per user (`users.csrf`) and replaced whenever any of that user's sessions makes a new one. Someone signed in on two devices has the older one's next form refused, which also signs that device out. Found while testing 0.15.0. | — (on the Later list) |

### Status on `claude/development`

Everything above marked with a release is fixed there and was re-tested on a fresh install
(PHP 8.4, MySQL 9.4) and on an upgrade from a database created by the original installer.
Items marked "—" are still open.

### Performance / housekeeping (documented, not changed)

- `init.php` recompiles and minifies all SCSS on **every request**, including API calls and the
  hidden CSRF `<img>`. *Gated behind the "Recompile sass" admin setting in 0.4.0; it defaults to
  on, so turn it off in production.*
- Images are stored as `BLOB`s inline in each row and base64-inlined into every page; the
  `media` table in the design diagram is the intended replacement. *Done in 0.8.0; the unused
  BLOB columns are still there to be dropped later.*
- `Classes/classes.php` `eval`s `use` statements that have no effect at file scope.
- `natxet/cssmin` and `tubalmartin/cssmin` are both required; only the latter is used.
- `.DS_Store` is committed despite being in `.gitignore`.
- No tests, although `phpunit/phpunit` is in `require-dev`.

## Local dev setup used for the audit

```bash
composer install
```

The front controller relies on Apache's cwd being the docroot. For `php -S`, use a router
that serves real files and falls through to `index.php`:

```php
<?php
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if ($path !== '/' && is_file($_SERVER['DOCUMENT_ROOT'] . $path)) {
    return false;
}
chdir($_SERVER['DOCUMENT_ROOT']);
require $_SERVER['DOCUMENT_ROOT'] . '/index.php';
```

```bash
php -S localhost:8089 -t . router.php
```

Then open `/install.php`. A non-default MySQL port can be given in the host field as
`127.0.0.1;port=33306`.
