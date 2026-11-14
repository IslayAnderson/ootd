# Decisions to confirm

Work on the [federation roadmap](FEDERATION.md) is being done autonomously. Where a choice was
needed, the recommended option was taken and logged here so it can be confirmed or reversed.
Nothing here is final until the owner signs it off.

Status: **assumed** (built that way, awaiting confirmation) or **open** (couldn't be assumed,
needs an answer before the work that depends on it).

## From the roadmap

| # | Decision | Taken | Status |
| --- | --- | --- | --- |
| R1 | Federation protocol | ActivityPub | assumed |
| R2 | What federates | Profiles, outfits, blogs, follows; garments between OOTD instances only; wears and insights never | assumed |
| R3 | Visibility of posts made before federation | Kept visible to members only; see D1 | assumed |
| R4 | Cloud model | One container + database per customer, same image as self-host | assumed |
| R5 | ActivityPub library or hand-rolled | Hand-rolled, after the 0.10.0 spike (reasons in FEDERATION.md, decision 5) | assumed |
| R6 | Licence | None chosen; AGPL-3.0 suggested. Nothing is relicensed without the owner | **open** |

## Made while building

| # | Release | Decision | Why | Status |
| --- | --- | --- | --- | --- |
| D1 | 0.7.0 | Added a **Members** visibility (anyone signed in to this instance) and gave every existing post that, instead of "followers only" as the roadmap said. | Nobody follows anyone yet, so "followers only" would have hidden every old post from every other member once follows arrive in 0.9.0. Members is exactly how posts behaved before. | assumed |
| D2 | 0.7.0 | New posts default to Members; the owner picks Public or Only me per post. | Nothing becomes public by accident. | assumed |
| D3 | 0.7.0 | Guests see a profile's username and public posts, but never the real name. | Names were only ever visible inside the closed site. | assumed |
| D4 | 0.7.0 | Wear history ("Last worn") is shown only to its owner, including to other members. | Wears are private habit data (R2); members could previously see it (AUDIT F17). | assumed |
| D5 | 0.7.0 | `/user/<username>` stays the profile address; `/@username` redirects to it. | Fewer link changes now; the ActivityPub actor id is decided in 0.11.0. | assumed |
| D6 | 0.7.0 | Deletes are soft (`deleted_at`) with no purge yet. | Needed to send `Delete` to other servers later; a purge job can come with the job runner. | assumed |
| D7 | 0.7.0 | Edits change text, tags, linked garments and visibility, not photos. | Photos move to the media store in 0.8.0. | assumed |
| D8 | 0.7.0 | `/privacy` is readable without signing in. | It's linked from the footer on public pages. | assumed |
| D9 | 0.7.1 | An early-design `invites` table found during upgrade is renamed `invites_legacy`, not converted or dropped. | Its columns (`invitee`, `token`, `confirmed`) don't map cleanly onto the current flow; keeping it loses nothing. | assumed |
| D10 | 0.7.1 | Staging is upgraded in place rather than wiped and reinstalled. | Exercises the real upgrade path on old data; a full backup was taken first (`/root/ootd-backups` on the server). | assumed |
| D11 | 0.8.0 | The old image BLOB columns are kept (unused) after the move to the media store, to be dropped in 0.10.0. | A rollback to 0.7.x still has its images; costs disk space until then. | assumed |
| D12 | 0.8.0 | Uploads are stored as 1600px and 480px versions only; the original file isn't kept. Photos become JPEG (quality 85); images with transparency stay PNG. | Strips location data with no way to leak it later, and keeps storage small. Means originals can't be re-processed later. | assumed |
| D13 | 0.8.0 | Images are served through PHP (`/media/...`) with a visibility check, not straight from disk or a public bucket. | Members-only and private photos stay private. Public ones get long cache headers to offset the cost. | assumed |
| D14 | 0.8.0 | Profile photos are always visible, including to people who aren't signed in. | They're shown on public profiles and next to public posts. | assumed |
| D15 | 0.9.0 | Follows are accepted automatically; approving followers is opt-in per account. | Matches Mastodon's default and the old behaviour, where members could see each other's posts without asking. | assumed |
| D16 | 0.9.0 | Members visibility stays the default for new posts (D2), with Followers as an extra choice. | Changing the default to Followers would hide new posts from members nobody has followed yet. | assumed |
| D17 | 0.9.0 | Photos of deleted posts are deleted for good 30 days later; the post row itself stays (soft-deleted). | Gives a window to undo, and keeps the row so a `Delete` can still be federated. | assumed |
| D18 | 0.9.0 | Follower and following counts are public on profiles, the list of who isn't. | Counts are standard on the fediverse; the lists reveal who knows whom. | assumed |
| D19 | 0.10.0 | Registration modes are **closed / invite / open**. The roadmap's "approval" mode is the existing invite flow (request, admin approves). Invite stays the default. | Two names for one flow would confuse admins. | assumed |
| D20 | 0.10.0 | Admins can change the default visibility of new posts; it stays Members (D2, D16). | Lets a public-facing server default to Public without code changes. | assumed |
| D21 | 0.10.0 | Password reset links work once, for an hour; at most 3 an hour per account; resetting signs the account out everywhere. The form gives the same answer whether or not the email has an account. | Standard protections against takeover and account enumeration. | assumed |
| D22 | 0.10.0 | Without SMTP settings, emails are written to `tmp/mail.log` (not web-readable) instead of being dropped. | A fresh or staging install can still hand out reset and invite links. | assumed |
| D23 | 0.10.0 | Email is sent over SMTP with PHPMailer, not through the Brevo API code found on staging (Q6). | Works with any provider, including Brevo's SMTP relay, and needs no provider SDK. | assumed |
| D24 | 0.10.0 | TinyMCE is updated to 7.9.3, not 8.x, although one advisory remains for 7.9.3. | The advisory is in the media plugin, which OOTD doesn't load, and rich text is sanitised on the server. A major upgrade can come with other editor work. | assumed |
| D25 | 0.10.0 | The data export leaves out deleted posts and other people's content, and contains the 1600px photos (originals aren't kept, D12). | It's the account's own current data. | assumed |
| D26 | 0.10.0 | `/health` is public but only says ok or fail per check; details are on the command line. | Monitors need no login; versions and paths aren't disclosed. | assumed |
| D27 | 0.10.0 | The old image BLOB columns are dropped in schema 7, but only from a table where every image was moved. | Planned in D11. A rollback to 0.7.x would now need a backup. | assumed |
| D28 | 0.10.0 | Docker: one Apache + PHP image for web and worker, MariaDB 11.4, CSS compiled at build. | Simplest image that honours the same `.htaccess` as PHP hosts; one image keeps self-host and cloud identical (R4). | assumed |

## Questions

| # | Question | Context |
| --- | --- | --- |
| Q1 | ~~May Claude push `claude/main` to GitHub?~~ **Answered:** push to staging whenever a release is complete. | `claude/main` and `claude/development` are pushed after each release; the files still reach the server by the SSH procedure (ROADMAP, "Staging"), since the server holds files that aren't in the repo (Q6). |
| Q2 | Should staging's `.env` move fully to the current format? | It used `DEV_/STAGE_/PROD_` prefixed keys that no version of the code reads. The current `DB_*` keys and `APP_URL` were added alongside them; the old keys were left in place. |
| Q3 | What were the `profile`, `profile_options` and `size_*` tables (and `garments.size`) for? | They're in the staging database but in no branch of the code. Left untouched. If sizing is a feature you want, it can go on the roadmap. |
| Q4 | Is the New Relic browser agent on staging intentional? | The server injects New Relic's script into every page. If it stays, the privacy page should mention it (third-party requests, AUDIT F15). |
| Q5 | Which S3-compatible provider (if any) should cloud and larger self-hosts use? | The S3 driver signs requests correctly against AWS's published example but hasn't run against a live bucket yet. |
| Q6 | Should the email code found only on staging come into the repo? | `Classes/mail/Mail.php` (Brevo invite emails), `Pages/onboard.php` and `partials/email_templates/invite.php` are on the server but in no branch. They were left in place on deploy and copied for safekeeping. They need `brevo/brevo-php` and Guzzle, which aren't in `composer.json`. The roadmap puts email in 0.10.0. |
| Q7 | May Claude add a cron job on staging for `bin/cron.php` (every minute, as `ide`)? | 0.9.0's background jobs only run when something runs them. Not added, since it's a standing change to your server: a Plesk scheduled task or `crontab -u ide` line `* * * * * /opt/plesk/php/8.3/bin/php /var/www/vhosts/islayanderson.co.uk/ootd.Islayanderson.co.uk/bin/cron.php`. Until then nothing is purged, which is harmless. |
| Q8 | Should OOTD serve its own fonts and charting library instead of Google Fonts and cdnjs? | AUDIT F15. Self-hosting means committing the font files (Newsreader and Inter Tight are under the SIL Open Font License) and Chartist. It removes the last third-party requests, which matters more once strangers can sign up. |
| Q9 | Which SMTP account should staging use? | Email works from 0.10.0 but staging has no `MAIL_*` settings, so reset and invite emails go to `tmp/mail.log` there. Brevo's SMTP relay would reuse the account the old code used. |
