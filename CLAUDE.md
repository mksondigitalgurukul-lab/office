# CLAUDE.md — Digital Ali Pro OMS

Guidance for Claude (or any future contributor) working in this repository.

## Project

**Digital Ali Pro OMS** is a single-tenant Office Management System for one
company's own staff (not multi-company / multi-tenant). It runs on plain PHP
(no framework) + MySQL over PDO, with session-based auth, deployed to cPanel
shared hosting at `https://www.digitalalipro.in/office`.

This is a **multi-prompt build**. This document reflects **Prompt 2 of 5**:
the foundation (Prompt 1) plus staff management, staff login, and
per-employee work-time overrides with full history. Attendance check-in,
leave/WFH requests, and payout are not built yet.

## Folder structure

```
/office
  /admin              Admin panel pages (login-protected except login.php)
    login.php         Email + password login
    logout.php         Destroys session, redirects to login
    dashboard.php      Post-login landing page + nav stub
    /staff            Staff management (login-protected, admin session)
      index.php       List staff — filter by status/department/work_mode, search by name/email
      add.php         Add staff form + create (initial password or auto-generate, shown once)
      edit.php        Edit staff fields + status (active/inactive = soft delete)
      view.php        Staff profile: current work timing + full timing history
      set-timing.php  Insert a new staff_work_time_history row (default or custom hours)
  /staff              Staff-facing pages (staff session, separate from admin)
    login.php         Email + password login for staff accounts
    logout.php         Destroys staff session, redirects to login
    dashboard.php      Welcome + work mode/timing + change-password form + nav stub
  /assets
    /css/style.css     Shared styles for all pages
    /js/main.js         Small shared UI behaviors (confirm dialogs)
  /includes
    db.php              PDO connection (getDB())
    auth.php            Admin session helpers: requireLogin(), loginAdmin(), logoutAdmin(), currentAdmin()
    staff_auth.php       Staff session helpers: requireStaffLogin(), loginStaff(), logoutStaff(), currentStaff()
    functions.php       h() escaping helper, getSetting()/setSetting(), getCurrentWorkTiming()
  /sql
    001_admins.sql       Schema file — admins table
    002_settings.sql     Schema file — settings table + seed rows
    003_office_locations.sql   Schema file — office_locations table
    004_holidays.sql     Schema file — holidays table (+ commented holiday_staff stub)
    005_staff.sql         Schema file — staff table
    006_staff_work_time_history.sql   Schema file — staff_work_time_history table
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
| `staff` | Employee records + their own login credentials. `work_mode` is `office`/`wfh`/`hybrid`. `status` is `active`/`inactive` (`inactive` = soft delete — the record is kept, and inactive staff cannot log in). `created_by` references the admin who created the record. |
| `staff_work_time_history` | Append-only log of every work-timing change for a staff member — see "Work timing history convention" below. `set_by` references the admin who recorded the change. |

## Work timing history convention (important)

`staff_work_time_history` is **append-only**: whenever an employee's work
timing changes, `admin/staff/set-timing.php` always **INSERTs a new row**.
Existing rows are never updated or deleted. This matters for every future
prompt that touches attendance or reports — always check the row that was
*effective on the date in question*, not just "whatever the staff record
says now".

- The **current** timing for a staff member on a given date = the row in
  `staff_work_time_history` with the latest `effective_from <= that date`.
- A row with `work_start_time`/`work_end_time` both `NULL` means "use the
  universal `settings.default_work_start_time`/`default_work_end_time`
  from this date forward" — an explicit revert-to-default, which is also
  recorded as a row so the history stays complete (it does not delete
  prior override rows).
- If a staff member has **no rows at all** yet, they also use the
  universal default.
- `effective_from` can be set in the future (e.g. a timing change that
  starts next month) — it simply won't be picked as "current" until that
  date arrives, while older/current rows keep resolving normally.
- The resolution logic lives in `includes/functions.php` as
  `getCurrentWorkTiming(int $staffId, ?string $onDate = null): array`,
  used by both `admin/staff/view.php` and `staff/dashboard.php`. Reuse
  this helper rather than re-implementing the "latest effective_from"
  query elsewhere.

## Auth approach

- Session-based (PHP native sessions), no JWT/tokens. **Admin and staff
  auth are two entirely separate systems** — different helper files,
  different session data, different session cookie — so an admin and a
  staff account can be logged in from the same browser at once without
  colliding.

### Admin auth (`includes/auth.php`)

- Uses the default PHP session (cookie `PHPSESSID`).
  - `requireLogin(string $loginPath)` — redirects to `$loginPath` if
    `$_SESSION['admin_id']` isn't set. Every protected page calls this with
    the correct relative path to `login.php` (e.g. `'login.php'` from
    `/admin/*.php`, `'../login.php'` from `/admin/staff/*.php`,
    `'../admin/login.php'` from `/sql/index.php`).
  - `loginAdmin(array $admin)` — regenerates the session ID and stores
    `admin_id`, `admin_name`, `admin_email`, `admin_role` in `$_SESSION`.
  - `logoutAdmin()` — clears the session and destroys it.
  - `currentAdmin(): ?array` — returns the logged-in admin's session data,
    or `null`.
- Two roles exist in the schema (`admin`, `manager`) but no role-based
  permission differences are implemented yet — that's for a later prompt
  once there's a reason to restrict a page to `admin` only.

### Staff auth (`includes/staff_auth.php`)

- Uses its **own named session** (`session_name('office_staff_sess')`),
  a separate cookie from the admin session above — this is the mechanism
  that keeps the two namespaces from colliding.
  - `requireStaffLogin(string $loginPath)` — redirects if
    `$_SESSION['staff_id']` isn't set.
  - `loginStaff(array $staff)` — regenerates the session ID and stores
    `staff_id`, `staff_name`, `staff_email` in `$_SESSION`.
  - `logoutStaff()` — clears the session and destroys it.
  - `currentStaff(): ?array` — returns the logged-in staff member's
    session data, or `null`.
- Login checks `status = 'active'` — inactive (soft-deleted) staff cannot
  log in even with a correct password.
- Staff can only change their own password from `/staff/dashboard.php`;
  no other self-service field editing exists yet.

### Shared

- Passwords are hashed with `password_hash()` / verified with
  `password_verify()`. Never store or log plaintext passwords.

## What's built so far (Prompts 1-2/5)

**Prompt 1 — Foundation:**
- Folder skeleton described above.
- `admins`, `settings`, `office_locations`, `holidays` tables.
- `sql/index.php` schema runner + DB dashboard + ad-hoc SQL tool.
- `create-admin.php` — one-time first-admin creation (CLI or browser form),
  blocks itself once any admin row exists.
- `/admin/login.php`, `/admin/logout.php`, `/admin/dashboard.php` with a nav
  stub (Staff, Attendance, Leave, Payout, Reports, Settings, DB Tools).

**Prompt 2 — Staff management:**
- `staff`, `staff_work_time_history` tables.
- Full staff CRUD from the admin side (`/admin/staff/`): list with
  filter/search, add (with initial or auto-generated password), edit
  (including status = soft delete), profile view.
- Work-timing override tool (`/admin/staff/set-timing.php`) that always
  inserts a new `staff_work_time_history` row — see "Work timing history
  convention" above.
- Staff-side login/logout/dashboard (`/staff/`) with its own session
  namespace, showing current work mode/timing and a self-service password
  change.
- Nav links across `/admin/dashboard.php` and `/sql/index.php` updated to
  point at the real `/admin/staff/index.php`.
- Verified end-to-end against a live MariaDB instance: schema auto-create
  for the two new tables, staff CRUD, filters/search, soft delete blocking
  staff login, work-timing resolution for past/current/future
  `effective_from` dates and explicit revert-to-default, staff login,
  password change, and simultaneous admin+staff sessions in one browser.

Attendance, leave/WFH, and payout are **not built yet** (Prompts 3-5).

## What's planned (not yet built)

- **Prompt 3:** Attendance / WiFi-based check-in (using `office_locations`).
- **Prompt 4:** Leave & WFH requests (will likely use/extend `holidays` +
  the commented-out `holiday_staff` stub).
- **Prompt 5:** Payout & reports.

Do not build any of the above ahead of schedule.

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
