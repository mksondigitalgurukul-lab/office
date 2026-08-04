# CLAUDE.md — Digital Ali Pro OMS

Guidance for Claude (or any future contributor) working in this repository.

## Project

**Digital Ali Pro OMS** is a single-tenant Office Management System for one
company's own staff (not multi-company / multi-tenant). It runs on plain PHP
(no framework) + MySQL over PDO, with session-based auth, deployed to cPanel
shared hosting at `https://www.digitalalipro.in/office`.

This is a **multi-prompt build**. This document reflects **Prompt 1 of 5**:
the foundation only — project skeleton, schema runner, and admin login.
Nothing else is built yet.

## Folder structure

```
/office
  /admin              Admin panel pages (login-protected except login.php)
    login.php         Email + password login
    logout.php         Destroys session, redirects to login
    dashboard.php      Post-login landing page + nav stub
  /assets
    /css/style.css     Shared styles for all pages
    /js/main.js         Small shared UI behaviors (confirm dialogs)
  /includes
    db.php              PDO connection (getDB())
    auth.php            Session helpers: requireLogin(), loginAdmin(), logoutAdmin(), currentAdmin()
    functions.php       h() escaping helper, getSetting()/setSetting()
  /sql
    001_admins.sql       Schema file — admins table
    002_settings.sql     Schema file — settings table + seed rows
    003_office_locations.sql   Schema file — office_locations table
    004_holidays.sql     Schema file — holidays table (+ commented holiday_staff stub)
    index.php            Schema runner + DB dashboard (see below)
  config.php            DB credentials (placeholders in git)
  .gitignore
  index.php              Redirects to /admin/login.php
  create-admin.php       One-time script to create the first admin
  CLAUDE.md             This file
  README.md             Setup / deployment instructions
```

## Database connection

- `includes/db.php` exposes `getDB(): PDO`, a lazily-created singleton PDO
  connection using the constants defined in `config.php`
  (`DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`).
- PDO is configured with `ERRMODE_EXCEPTION` and `FETCH_ASSOC` by default.
- `config.php` in git only ever holds placeholder values. On the real server,
  edit it in place with the actual cPanel MySQL credentials — don't commit
  real credentials back to the repo.

## Schema convention (important)

**All schema changes go through numbered files in `/sql`.** Never edit the
database by hand in phpMyAdmin.

1. Add a new file `sql/NNN_description.sql` (next number, one concern per
   file) containing `CREATE TABLE IF NOT EXISTS ...` (and, if needed, an
   idempotent seed `INSERT ... ON DUPLICATE KEY UPDATE ...`).
2. Visit `/sql/index.php` (logged in as admin) — it automatically detects
   the new file and runs it because the table it creates doesn't exist yet.
3. Update the **table list** below in this file to describe the new table.
4. If a table's shape needs to change later (add a column, etc.), either
   add a fresh numbered `.sql` file with `ALTER TABLE ... ADD COLUMN IF NOT
   EXISTS`-style guards where possible, or use the ad-hoc SQL box on
   `/sql/index.php` for one-off manual fixes (every statement run there is
   logged to `sql/schema_log.txt`, which is gitignored).

### How `sql/index.php` works

- Reads every `*.sql` file in `/sql`, sorted naturally by filename (so the
  `NNN_` prefix controls order).
- For each file, extracts the table name(s) it creates (via the
  `CREATE TABLE IF NOT EXISTS` statement, ignoring commented-out stub SQL).
- If the table doesn't exist yet, it runs the file's statements automatically
  and shows a "created just now" badge.
- If the table already exists, it shows that table's current columns
  instead of touching it.
- Each file also has a manual **Re-run** button — safe because every
  `CREATE TABLE` uses `IF NOT EXISTS` and seed `INSERT`s use
  `ON DUPLICATE KEY UPDATE`, so re-running never destroys data.
- An **ad-hoc SQL** textarea lets an admin run one-off statements (e.g. a
  manual `ALTER TABLE`); every execution (success or failure) is appended
  to `sql/schema_log.txt` with a timestamp and the admin's email.
- A **dashboard** section lists every table currently in the database, its
  row count, and its column names.
- **Bootstrap exception:** on a brand-new install there is no admin account
  yet, so normal login-gated access is impossible. While the `admins` table
  is missing or has zero rows, `/sql/index.php` is reachable without login
  (a visible "Setup mode" banner marks this). The instant an admin account
  exists (via `create-admin.php`), the page locks behind `requireLogin()`
  like every other admin page. Do not remove this exception without adding
  another way to bootstrap the very first admin.

## Tables (update this section whenever a table is added)

| Table | Purpose |
|---|---|
| `admins` | Admin/manager login accounts. `role` is `admin` or `manager`. Password stored as `password_hash()`. |
| `settings` | Key/value app config (`setting_key` PK, `setting_value`). Seeded with `company_name`, `default_work_start_time`, `default_work_end_time`, `timezone`. |
| `office_locations` | Named office branches with an `ip_address`, for future WiFi-based attendance check-in. `is_active` flag. |
| `holidays` | Company holiday dates. `applies_to` is `all` or `specific`. A `holiday_staff` join table is stubbed (commented out) in `004_holidays.sql` for targeting specific staff once the staff table exists — **not built yet**. |

## Auth approach

- Session-based (PHP native sessions), no JWT/tokens.
- `includes/auth.php`:
  - `requireLogin(string $loginPath)` — redirects to `$loginPath` if
    `$_SESSION['admin_id']` isn't set. Every protected page calls this with
    the correct relative path to `login.php` (e.g. `'login.php'` from
    `/admin/*.php`, `'../admin/login.php'` from `/sql/index.php`).
  - `loginAdmin(array $admin)` — regenerates the session ID and stores
    `admin_id`, `admin_name`, `admin_email`, `admin_role` in `$_SESSION`.
  - `logoutAdmin()` — clears the session and destroys it.
  - `currentAdmin(): ?array` — returns the logged-in admin's session data,
    or `null`.
- Two roles exist in the schema (`admin`, `manager`) but this prompt does
  not yet implement any role-based permission differences — that's for a
  later prompt once there's more than one page to restrict.
- Passwords are hashed with `password_hash()` / verified with
  `password_verify()`. Never store or log plaintext passwords.

## What's built so far (Prompt 1/5)

- Folder skeleton described above.
- `admins`, `settings`, `office_locations`, `holidays` tables.
- `sql/index.php` schema runner + DB dashboard + ad-hoc SQL tool.
- `create-admin.php` — one-time first-admin creation (CLI or browser form),
  blocks itself once any admin row exists.
- `/admin/login.php`, `/admin/logout.php`, `/admin/dashboard.php` with a nav
  stub (Staff, Attendance, Leave, Payout, Reports, Settings, DB Tools) —
  the linked pages besides DB Tools **do not exist yet**.
- Verified end-to-end against a live MariaDB instance: schema auto-create,
  re-run, ad-hoc ALTER + logging, login/logout, session protection.

## What's planned (not yet built)

- **Prompt 2:** Staff management (staff table, CRUD, profile fields).
- **Prompt 3:** Attendance / WiFi-based check-in (using `office_locations`).
- **Prompt 4:** Leave & WFH requests (will likely use/extend `holidays` +
  the commented-out `holiday_staff` stub).
- **Prompt 5:** Payout & reports.

Do not build any of the above ahead of schedule — this prompt is foundation
only.

## Conventions

- Plain procedural PHP, no framework, no autoloader — each file
  `require_once`s exactly what it needs from `/includes`.
- All DB access goes through PDO with prepared statements
  (`PDO::ATTR_EMULATE_PREPARES` disabled) — never interpolate user input
  into SQL strings.
- All dynamic HTML output is escaped with the `h()` helper
  (`includes/functions.php`), which wraps `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')`.
- SQL filenames: `NNN_description.sql`, zero-padded three digits, one
  concern (table) per file, ascending order = execution order.
- Table/column names: `snake_case`. PHP variables/functions: `camelCase`.
  Constants: `UPPER_SNAKE_CASE`.
- Mobile-friendly, minimal UI: shared `assets/css/style.css`, no CSS
  framework/build step, no JS framework — `assets/js/main.js` is plain JS.
