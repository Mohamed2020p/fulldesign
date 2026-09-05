# GodsForum

An old school message board built with plain PHP 8, MySQL, and Tailwind CSS.
No framework, no JavaScript build step for the front end, no infinite scroll.
Threads stay where you left them.

![GodsForum](assets/img/logo.png)

---

## What is included

**Public forum**

| Page | File | Purpose |
| --- | --- | --- |
| Board index | `index.php` | Categories, boards, last post per board, live statistics |
| Board | `board.php` | Paginated topic list with pinned and locked markers |
| Topic | `topic.php` | Paginated posts, author cards, signatures, quick reply |
| New topic | `new_topic.php` | Opens a discussion in a board |
| Edit post | `edit_post.php` | Author or staff editing, with an "edited" marker |
| Report | `report.php` | Sends a post to the staff queue |
| Recent | `recent.php` | Newest activity across every board |
| Members | `members.php` | Searchable, sortable member directory |
| Profile | `profile.php` | Public profile plus avatar, signature and password settings |
| Search | `search.php` | Searches titles, post bodies, or both |
| Rules | `rules.php` | The six board rules and how they are enforced |
| Sign in / Register / Sign out | `login.php`, `register.php`, `logout.php` | Account handling |

**Admin control room** (`/admin`, staff only)

| Page | Purpose |
| --- | --- |
| `admin/index.php` | Dashboard: counts, open reports, newest members, latest posts |
| `admin/categories.php` | Create, edit, reorder and delete categories |
| `admin/boards.php` | Create, edit, move, lock and delete boards |
| `admin/topics.php` | Pin, lock, move between boards, delete topics |
| `admin/posts.php` | Search, edit and delete any post |
| `admin/reports.php` | Report queue with resolve, reopen and delete actions |
| `admin/users.php` | Roles, suspensions and account deletion |
| `admin/settings.php` | Board name, tagline, welcome text, registration switch |
| `admin/logs.php` | Audit trail of every staff action and recent sign in attempts |

Moderators see everything except **Settings** and **Staff log**, which are
reserved for administrators.

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
| `GF_BASE_URL` | *(empty)* | Subfolder path, e.g. `/godsforum`, no trailing slash |
| `GF_HTTPS` | `false` | Set true to mark session cookies `Secure` |
| `GF_DEBUG` | `false` | Show PHP errors on screen. Keep off in production |

### 4. Make uploads writable

```bash
chmod 775 uploads/avatars
```

### 5. Sign in

Open the site and sign in with a seeded account.

| Username | Role | Password |
| --- | --- | --- |
| `zeus` | Administrator | `Password123!` |
| `athena` | Moderator | `Password123!` |
| `hermes`, `hestia`, `prometheus` | Member | `Password123!` |

> **Change every seeded password immediately**, and delete the accounts you do
> not need from the control room.

---

## Security

The forum was written defensively from the first line.

**SQL injection** — every database call goes through `includes/db.php`, which
prepares the statement and binds each value with an explicit PDO type.
`PDO::ATTR_EMULATE_PREPARES` is off, so the server does the parameterising.
User input is never concatenated into SQL; even `LIKE` searches escape `%`
and `_` before binding.

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

Eight InnoDB tables in `utf8mb4`, with foreign keys, indexes on every join and
sort column, and full text indexes on titles and post bodies.

```
categories ──< boards ──< topics ──< posts ──< reports
                            │         │
                            └── users ┘
login_attempts     admin_log     settings
```

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

- All 30 PHP files parse without a syntax error.
- The stylesheet passes stylelint and every declaration parses against the CSS
  specification with css-tree.
- Rendered HTML from all public and admin pages passes `html-validate`.
- An 81 check integration suite exercised every page and every form: guest
  browsing, sign in and sign out, registration validation, posting, replying,
  editing, reporting, all nine admin screens, all moderation actions,
  permission boundaries, CSRF rejection, SQL injection attempts and XSS
  escaping. All 81 checks pass.

---

## Project layout

```
.
├── index.php               board index
├── board.php               topic list
├── topic.php               posts and reply form
├── new_topic.php           create a topic
├── edit_post.php           edit a post
├── report.php              report a post
├── recent.php              recent activity
├── members.php             member directory
├── profile.php             profile and account settings
├── search.php              search
├── rules.php               board rules
├── login.php  register.php  logout.php
├── admin/
│   ├── layout.php          control room shell
│   ├── index.php           dashboard
│   ├── categories.php  boards.php  topics.php  posts.php
│   ├── reports.php     users.php   settings.php  logs.php
├── includes/
│   ├── config.php          configuration constants
│   ├── db.php              PDO layer, prepared statements only
│   ├── functions.php       sessions, CSRF, escaping, auth, helpers
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
