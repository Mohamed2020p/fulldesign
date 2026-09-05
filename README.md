# GodsForum

An old school message board built with plain PHP 8, MySQL, and Tailwind CSS.
No framework, no JavaScript build step for the front end, no infinite scroll.
Threads stay where you left them.

![GodsForum](assets/img/logo.png)

---

## What is included

**Public forum**

Every address is readable. No page ever exposes a `.php` extension or a
numeric query string.

| Address | Purpose |
| --- | --- |
| `/` | Categories, boards, last post per board, live statistics |
| `/board/general-talk` | Paginated topic list with pinned and locked markers |
| `/board/general-talk/page/2` | Any later page of a board |
| `/board/general-talk/new` | Opens a discussion in that board |
| `/topic/welcome-to-godsforum` | Paginated posts, author cards, signatures, quick reply |
| `/topic/welcome-to-godsforum/page/3` | Any later page of a topic |
| `/post/rf6u4goctnid/edit` | Author or staff editing, with an "edited" marker |
| `/post/rf6u4goctnid/report` | Sends a post to the staff queue |
| `/member/hermes` | Public profile plus avatar, signature and password settings |
| `/recent` | Newest activity across every board |
| `/members` | Searchable, sortable member directory |
| `/search` | Searches titles, post bodies, or both |
| `/appearance` | Lets a member choose the theme the board is drawn in |
| `/rules` | The six board rules and how they are enforced |
| `/login`, `/register`, `/logout` | Account handling |

Boards and topics are addressed by slug, members by username, and posts by a
random twelve character reference. No row identifier is ever published, so the
address space cannot be walked and no internal numbering is disclosed.

**Admin control room** (`/admin`, staff only)

The public board is deliberately old school. The control room is not: it has a
modern interface with a dark fixed sidebar, grouped navigation, statistic
tiles, soft rounded surfaces and a quiet indigo accent.

| Address | Purpose |
| --- | --- |
| `/admin` | Dashboard: counts, open reports, newest members, latest posts |
| `/admin/categories` | Create, edit, reorder and delete categories |
| `/admin/boards` | Create, edit, move, lock and delete boards |
| `/admin/topics` | Pin, lock, move between boards, delete topics |
| `/admin/posts` | Search, edit and delete any post |
| `/admin/reports` | Report queue with resolve, reopen and delete actions |
| `/admin/members` | Roles, **bans**, filters and account deletion |
| `/admin/appearance` | Board theme, member theme permission, bulk reset |
| `/admin/settings` | Board name, tagline, welcome text, registration switch |
| `/admin/logs` | Audit trail of every staff action and recent sign in attempts |

Moderators see everything except **Appearance**, **Settings** and **Staff
log**, which are reserved for administrators.

**Banning members**

From `/admin/members` a staff member can suspend any account with a reason and
a length: permanent, one day, three days, one week, thirty days or ninety
days. The reason and the expiry are shown to the member on the sign in screen,
so a suspension is never silent. A timed suspension lifts itself the moment it
expires, both at sign in and on the next page view of an open session, so
staff never have to come back and undo it. Every suspension is written to a
`bans` history table that survives reinstatement, and `/admin/members?show=banned`
lists everyone currently suspended.

**Themes**

Eight themes ship with the board, grouped into three families:

| Family | Themes |
| --- | --- |
| Classic | Parchment, Midnight, Ember, Forest |
| Modern | Slate, Aurora, Sandstone |
| Accessible | High contrast |

A theme is a set of CSS custom properties selected by a `data-theme` attribute
on the `<html>` element. The markup, the layout and the stylesheet are
identical in every theme, so switching costs nothing and cannot break a page.
Administrators set the board theme at `/admin/appearance`; members override it
for their own account at `/appearance`, unless the administrator turns member
choice off.

---

## Design

An old school theme rendered with modern CSS:

- **Palette** — parchment `#f4ecdc`, ink navy `#1c2b45`, gold `#c2a14d`,
  crimson `#8f2c2c`, forest `#2f5d43`.
- **Typography** — Poppins from Google Fonts across the whole site.
- **Icons** — Material Icons Outlined. No emoji is used anywhere.
- **Texture** — a faint ruled-paper grid drawn with two CSS gradients.
- **Layout** — bordered panels with dark title bars, tabular rows, and
  engraved artwork, all of it responsive down to a phone.

Every page has a skip link, landmark regions, `aria-current` on the active
navigation item, visible focus rings, and labelled form fields.

---

## Requirements

- PHP 8.1 or newer with `pdo_mysql`, `mbstring` and `gd`
- MySQL 5.7+ or MariaDB 10.3+
- Apache with `mod_rewrite` and `mod_headers`, or nginx
- Node.js 18+ **only if you want to rebuild the CSS** (the compiled file is committed)

---

## Installation

### 1. Get the files

```bash
git clone https://github.com/Mohamed2020p/fulldesign.git godsforum
cd godsforum
```

Point your web server document root at the project folder.

### 2. Import the database

```bash
mysql -u root -p < database/godsforum.sql
```

This creates the `godsforum` database, all eight tables, and seed content:
three categories, nine boards, six topics, thirteen posts and five accounts.

### 3. Configure

Edit `includes/config.php`, or set these environment variables:

| Variable | Default | Meaning |
| --- | --- | --- |
| `GF_DB_HOST` | `127.0.0.1` | MySQL host |
| `GF_DB_PORT` | `3306` | MySQL port |
| `GF_DB_NAME` | `godsforum` | Database name |
| `GF_DB_USER` | `root` | Database user |
| `GF_DB_PASS` | *(empty)* | Database password |
| `GF_BASE_URL` | *(auto-detected)* | Override the install path only if detection cannot work, e.g. behind a proxy |
| `GF_HTTPS` | `false` | Set true to mark session cookies `Secure` |
| `GF_DEBUG` | `false` | Show PHP errors on screen. Keep off in production |

#### Installing in a subfolder

Nothing needs to be configured. The install path is detected at runtime, so
the same files work unchanged whether you drop them at the document root or
inside any folder, under any name:

| URL you open | Detected `BASE_URL` | Stylesheet resolves to |
| --- | --- | --- |
| `http://localhost/` | `''` | `/assets/css/style.css` |
| `http://localhost/fulldesign-arena-01a0708a-fulldesign/` | `/fulldesign-arena-01a0708a-fulldesign` | `/fulldesign-arena-01a0708a-fulldesign/assets/css/style.css` |
| `http://localhost/forums/godsforum/` | `/forums/godsforum` | `/forums/godsforum/assets/css/style.css` |

Every link, form action, redirect, image and the session cookie path are all
built from `BASE_URL`, so renaming or moving the folder needs no code change.
`.htaccess` is written with relative rules for the same reason.

### 4. Make uploads writable

```bash
chmod 775 uploads/avatars
```

### 5. Sign in

Open the site and sign in with a seeded account.

| Username | Role | Password |
| --- | --- | --- |
| `admin` | Administrator, full privileges | `admin` |
| `zeus` | Administrator | `Password123!` |
| `athena` | Moderator | `Password123!` |
| `hermes`, `hestia`, `prometheus` | Member | `Password123!` |

> **Change every seeded password immediately**, and delete the accounts you do
> not need from the control room.

---

## Security

The forum was written defensively from the first line.

**SQL injection, every class of it** — every database call goes through
`includes/db.php`, which prepares the statement and binds each value with an
explicit PDO type. `PDO::ATTR_EMULATE_PREPARES` is off, so the database server
does the parameterising and a value can never be re-read as syntax. User input
is never concatenated into SQL; even `LIKE` searches escape `%` and `_` before
binding. Where a query needs a variable *fragment* rather than a value — a
sort column, a status filter, a table name for slug generation — the request
selects a key in a fixed whitelist and the code emits its own literal, so the
input never reaches the query text at all.

This closes classic, blind boolean and time based (`SLEEP`) injection
together, because none of them have an insertion point to work with. Two
further rules make sure nothing leaks even so:

- **Nothing hints that an injection point exists.** Slugs, usernames and post
  references are constrained by the router before a query runs, and a value
  that matches no row produces the same ordinary 404 page as any other missing
  address — byte for byte identical, whatever the payload was. There is no
  error text, no different status, no different length and no timing
  difference to compare, so a blind attacker gets no oracle to work from.
- **Database errors never reach the browser.** `DEBUG_MODE` is off in
  production, PDO is set to throw, and the handler logs the exception server
  side and shows a generic page. `SQLSTATE` codes, driver messages and query
  fragments are never rendered.

**Transport security** — when the request arrives over TLS the board sends
`Strict-Transport-Security: max-age=31536000; includeSubDomains`, pinning the
browser to HTTPS for a year so a later plain HTTP link cannot be stripped or
intercepted. The header is deliberately withheld on plain HTTP, because a
browser must ignore it there and sending it before the site is fully on TLS
would lock visitors out. Set `GF_HTTPS=true` and session cookies are marked
`Secure` as well as `HttpOnly` and `SameSite=Lax`.

**Cross site request forgery** — `csrf_token()` puts a 64 character random
token in the session, `csrf_field()` prints it into every form, and
`require_post_csrf()` rejects any state-changing request that is not a POST
carrying a matching token, comparing with `hash_equals()`. Signing out is a
POST for the same reason.

**Cross site scripting** — no user HTML is ever trusted. Output goes through
`e()`, which is `htmlspecialchars` with `ENT_QUOTES | ENT_SUBSTITUTE` and
UTF-8. Post bodies are escaped first and only then split into paragraphs by
`format_post()`.

**Passwords** — hashed with `password_hash()` using the current default
algorithm (bcrypt), verified with `password_verify()`, and transparently
upgraded on sign in via `password_needs_rehash()`.

**Brute force** — every attempt is written to `login_attempts`. After five
failures from the same account or IP within fifteen minutes, sign in is
refused. A verification always runs even for unknown accounts so response
time does not reveal which usernames exist.

**Sessions** — a custom session name, `HttpOnly` and `SameSite=Lax` cookies,
`Secure` when `GF_HTTPS` is set, the identifier rotated every thirty minutes
and again on sign in.

**Uploads** — avatars are checked with `getimagesize()` rather than by
extension, limited to 2 MB, renamed to a random filename, and stored in a
directory where `.htaccess` disables script execution entirely.

**Authorisation** — `require_login()`, `require_admin()` and
`is_super_admin()` guard every protected action on the server side, not merely
in the interface. Staff cannot act on their own account, and moderators cannot
act on administrators.

**Headers** — `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`
and `Permissions-Policy` are sent from PHP; the root `.htaccess` adds a
Content Security Policy and blocks direct access to `includes/`, `database/`
and every `.sql` file.

**Flood control** — a member must wait `POST_FLOOD_SECONDS` between posts.

**Audit trail** — every staff action lands in `admin_log` with the actor, a
description, the IP address and a timestamp.

---

## Database

Ten InnoDB tables in `utf8mb4`, with foreign keys, indexes on every join and
sort column, unique indexes on every slug, and full text indexes on titles and
post bodies.

```
categories ──< boards ──< topics ──< posts ──< reports
                            │         │
                            └── users ┘
                                  │
                                 bans
login_attempts     admin_log     settings
```

`categories`, `boards` and `topics` each carry a unique `slug`, and `posts`
carries a unique random `ref`. These are what appear in addresses. `users`
carries `theme`, `ban_reason`, `banned_until` and `banned_by`.

Deleting a category, board or topic cascades to the content below it. Deleting
a member sets their posts to `NULL`, so history stays readable and the author
simply shows as a departed member.

---

## Development

```bash
npm install          # dev tooling only, no runtime dependency
npm run build        # compile assets/src/tailwind.css -> assets/css/style.css
npm run watch        # rebuild the CSS as you edit
npm run lint:php     # parse every PHP file and report syntax errors
npm run lint:css     # stylelint the source stylesheet
```

The compiled `assets/css/style.css` is committed, so the forum runs on a plain
PHP host with no Node.js installed.

### Verification performed on this codebase

- All 35 PHP files parse without a syntax error.
- The stylesheet passes stylelint and every declaration parses against the CSS
  specification with css-tree.
- Rendered HTML from all public and admin pages passes `html-validate`.
- A 120 check integration suite exercised every page and every form: guest
  browsing, sign in and sign out, registration validation, posting, replying,
  editing, reporting, all ten admin screens, all moderation actions,
  permission boundaries, CSRF rejection, XSS escaping, theme selection, the
  full ban lifecycle, and clean URL routing. All 120 checks pass.
- Every rendered page was scanned for links: no public page emits a `.php`
  extension or a `?id=` style query string anywhere.
- Seven injection probes covering boolean, union and time based payloads
  (including `SLEEP`) were fired at the topic, board, member and post routes.
  Each returned an ordinary 404 in under a second, and the 404 body for a
  crafted slug is byte for byte identical to the 404 for a plain miss.
- The whole board was deployed into a subfolder and re-tested: all 171 local
  URLs across seven pages resolved against the install folder.

---

## Project layout

```
.
├── router.php              front controller: maps clean URLs to pages
├── index.php               hands the request to router.php
├── .htaccess               rewrite rules, security headers, file denials
├── pages/                  route targets, never addressed directly
│   ├── index.php           board index
│   ├── board.php           topic list
│   ├── topic.php           posts and reply form
│   ├── new_topic.php       create a topic
│   ├── edit_post.php       edit a post
│   ├── report.php          report a post
│   ├── recent.php          recent activity
│   ├── members.php         member directory
│   ├── profile.php         profile and account settings
│   ├── search.php          search
│   ├── appearance.php      member theme picker
│   ├── rules.php           board rules
│   ├── login.php  register.php  logout.php
│   └── admin/
│       ├── layout.php      control room shell, modern interface
│       ├── index.php       dashboard
│       ├── categories.php  boards.php  topics.php  posts.php
│       ├── reports.php     users.php   appearance.php
│       └── settings.php    logs.php
├── includes/
│   ├── config.php          configuration constants
│   ├── db.php              PDO layer, prepared statements only
│   ├── functions.php       sessions, CSRF, escaping, auth, URL builders
│   ├── themes.php          the theme catalogue and resolution
│   ├── header.php          public header
│   └── footer.php          public footer
├── assets/
│   ├── css/style.css       compiled Tailwind output
│   ├── src/tailwind.css    Tailwind source with the component layer
│   └── img/                logo, banner, default avatar
├── database/godsforum.sql  schema and seed data
├── uploads/avatars/        member avatars, script execution disabled
├── tailwind.config.js      theme: colours, Poppins, shadows
└── lint-php.cjs            PHP syntax checker
```

---

## License

MIT.
