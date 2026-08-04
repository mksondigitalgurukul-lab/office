# CLAUDE.md — Digital Ali Pro OMS

Guidance for Claude (or any future contributor) working in this repository.

## Project

**Digital Ali Pro OMS** is a single-tenant Office Management System for one
company's own staff (not multi-company / multi-tenant). It runs on plain PHP
(no framework) + MySQL over PDO, with session-based auth, deployed to cPanel
shared hosting at `https://www.digitalalipro.in/office`.

This is a **multi-prompt build**. This document reflects **Prompt 3 of 5**:
the foundation (Prompt 1), staff management (Prompt 2), and now daily
attendance check-in/check-out with office-WiFi verification, an admin
attendance monitor, and a daily absent-marking cron script. Leave/WFH
requests and payout are not built yet.

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
      view.php        Staff profile: current work timing + full timing history + link to attendance history
      set-timing.php  Insert a new staff_work_time_history row (default or custom hours)
    /attendance        Attendance monitor (login-protected, admin session)
      index.php       Today/date view — filter by department/work_mode, highlights late/unverified rows
      staff.php       Per-staff attendance history (last 60 records)
      edit.php        Manual add/edit of one staff's attendance row for one date (logs the edit in notes)
  /staff              Staff-facing pages (staff session, separate from admin)
    login.php         Email + password login for staff accounts
    logout.php         Destroys staff session, redirects to login
    dashboard.php      Welcome + work mode/timing + change-password form + nav stub
    attendance.php      Today's check-in/check-out status + buttons; holiday-skip
  /assets
    /css/style.css     Shared styles for all pages
    /js/main.js         Small shared UI behaviors (confirm dialogs)
  /includes
    db.php              PDO connection (getDB())
    auth.php            Admin session helpers: requireLogin(), loginAdmin(), logoutAdmin(), currentAdmin()
    staff_auth.php       Staff session helpers: requireStaffLogin(), loginStaff(), logoutStaff(), currentStaff()
    functions.php       h(), getSetting()/setSetting(), getCurrentWorkTiming(), and the attendance
                        helpers described below (getClientIp(), isOfficeIp(), etc.)
  /sql
    001_admins.sql       Schema file — admins table
    002_settings.sql     Schema file — settings table + seed rows
    003_office_locations.sql   Schema file — office_locations table
    004_holidays.sql     Schema file — holidays table (+ commented holiday_staff stub)
    005_staff.sql         Schema file — staff table
    006_staff_work_time_history.sql   Schema file — staff_work_time_history table
    007_attendance.sql    Schema file — attendance table + seeds attendance_grace_minutes setting
    index.php            Schema runner + DB dashboard + Admin Account section (see below)
    .htaccess             Blocks direct HTTP access to *.txt files (key.txt, schema_log.txt)
    key.txt               Admin management key — gitignored, created by the Admin Account section
  /cron
    mark-absent.php       Daily script: marks active staff with no attendance row for
                          yesterday as 'absent' (skips holidays) — see "Attendance" below
  config-exmaple.php     Tracked config template (DB credentials + CRON_SECRET placeholders)
  config.php            Copied from config-exmaple.php on deploy — gitignored, never committed
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
- `config.php` itself is **not tracked in git** (`.gitignore`) — only
  `config-exmaple.php` is committed, holding placeholder values. On the
  real server (or for local dev), copy it (`cp config-exmaple.php
  config.php`) and edit the copy with real credentials. This means a
  future `git pull` on the server never overwrites live credentials —
  don't commit `config.php` itself back to the repo.

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
- An **Admin Account** section provides an in-browser alternative/companion
  to `create-admin.php`:
  - While `admins` has zero rows, it shows a **Create Admin** form (name,
    email, password, plus a **management key** the operator chooses).
    That key is written to `sql/key.txt` on first use.
  - Once an admin exists, it instead shows an **Update Password** form
    (pick an existing admin, set a new password), gated by that same
    management key (checked with `hash_equals()` against `sql/key.txt`).
  - `sql/key.txt` is plain text, gitignored, `chmod 0600`, and blocked
    from direct HTTP access by `sql/.htaccess` (Apache — not enforced
    under `php -S` in local dev) — treat it as a shared secret the site
    operator keeps, separate from any admin's login password. There's no
    UI to change it; edit `sql/key.txt` on the server directly if it needs
    to change, or delete it to let the next admin-creation set a new one
    (only possible again once `admins` is empty).
  - Every create/reset via this section is logged to `schema_log.txt` like
    other DB Tools actions, but the log entry never contains the password
    or key — only the target admin's email.

## Tables (update this section whenever a table is added)

| Table | Purpose |
|---|---|
| `admins` | Admin/manager login accounts. `role` is `admin` or `manager`. Password stored as `password_hash()`. |
| `settings` | Key/value app config (`setting_key` PK, `setting_value`). Seeded with `company_name`, `default_work_start_time`, `default_work_end_time`, `timezone`, `attendance_grace_minutes`. |
| `office_locations` | Named office branches with an `ip_address`, for future WiFi-based attendance check-in. `is_active` flag. |
| `holidays` | Company holiday dates. `applies_to` is `all` or `specific`. A `holiday_staff` join table is stubbed (commented out) in `004_holidays.sql` for targeting specific staff once the staff table exists — **not built yet**. |
| `staff` | Employee records + their own login credentials. `work_mode` is `office`/`wfh`/`hybrid`. `status` is `active`/`inactive` (`inactive` = soft delete — the record is kept, and inactive staff cannot log in). `created_by` references the admin who created the record. |
| `staff_work_time_history` | Append-only log of every work-timing change for a staff member — see "Work timing history convention" below. `set_by` references the admin who recorded the change. |
| `attendance` | One row per staff member per day (unique on `staff_id` + `attendance_date`). `work_location` records how the check-in was verified; `status` is always computed by the app, never entered directly by staff — see "Attendance" below. |

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

## Attendance

One `attendance` row per staff member per day (`staff/attendance.php` for
check-in/out, `admin/attendance/*` for the monitor). All logic lives in
`includes/functions.php`: `getClientIp()`, `isOfficeIp()`,
`getAttendanceGraceMinutes()`, `getHolidayName()`, `computeCheckInStatus()`,
`isHalfDay()`.

- **Holiday skip:** if `getHolidayName($date)` returns non-null,
  `staff/attendance.php` shows "Holiday today" instead of check-in/out
  buttons, and the daily cron skips the date entirely — no attendance rows
  are created for a holiday at all (not even 'absent'). `applies_to =
  'specific'` is currently treated the same as `'all'` — per-staff holiday
  targeting via the (still unbuilt) `holiday_staff` table is deferred to
  Prompt 4, so **any** holiday row blocks attendance company-wide for now.
- **Check-in / `work_location`:** compares `getClientIp()` against active
  `office_locations.ip_address` rows (exact match, no CIDR support).
  - Match → `office_verified`.
  - No match, `staff.work_mode = 'wfh'` → `wfh` (no warning).
  - No match, `staff.work_mode` is `'office'` or `'hybrid'` → `unverified`,
    with an on-page warning that the check-in is flagged for admin review
    — but it still saves; nothing blocks it.
  - `office_manual` is only ever set by an admin's manual override
    (`admin/attendance/edit.php`), never by the staff-side check-in flow.
  - WFH-request-driven attendance (auto-treating a hybrid/office staff
    member's approved WFH request day as legitimate `wfh` instead of
    `unverified`) is **deferred to Prompt 4** — right now `work_location`
    only reflects what was entered/detected at check-in time.
- **`status` computation (never user-entered):**
  - At check-in: `computeCheckInStatus()` compares the check-in time
    against `getCurrentWorkTiming()`'s `start` plus
    `getAttendanceGraceMinutes()` (`settings.attendance_grace_minutes`,
    seeded to 15) → `'present'` if within the grace window, else `'late'`.
  - At check-out: if `isHalfDay()` finds the worked duration
    (`check_out - check_in`) is under **half** the scheduled shift length
    (`getCurrentWorkTiming()`'s `end - start`), status is overridden to
    `'half_day'` regardless of whether it was `'present'` or `'late'` at
    check-in. Otherwise the check-in status is left as-is.
  - `'absent'` is only ever set by `cron/mark-absent.php` (no check-in
    at all) or by an admin's manual override — never by the check-in/out
    flow itself.
- **One row per staff per day** is enforced by the DB unique key on
  (`staff_id`, `attendance_date`), not just app logic.
- **Manual overrides** (`admin/attendance/edit.php`) use
  `INSERT ... ON DUPLICATE KEY UPDATE` keyed on that same unique
  constraint, so the same form both adds a missing day and edits an
  existing one. `check_in_ip`/`check_out_ip` are only ever set by the
  real check-in/out flow — a manual edit never touches them (they're
  excluded from the `ON DUPLICATE KEY UPDATE` clause), so a genuine
  IP is never overwritten by a later correction. Every save appends
  `[Manually edited by {admin name} on {timestamp}]` plus any optional
  reason to `notes` — prior notes are kept, never overwritten.
- **`cron/mark-absent.php`** runs once daily (see README for cPanel
  scheduling) and, for **yesterday's** date only, inserts an `'absent'`
  row (`work_location = 'unverified'`) for every active staff member with
  no attendance row for that date — skipped entirely if yesterday was a
  holiday. Idempotent: safe to re-run, existing rows are left untouched.
  Runs via CLI with no auth; if triggered over HTTP instead (e.g. a
  cPanel "URL" cron job) it requires `?key=` to match `CRON_SECRET` in
  `config.php` (placeholder in `config-exmaple.php` — change it on the
  real server's `config.php`).

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

## What's built so far (Prompts 1-3/5)

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
- Also added since Prompt 2 shipped: a key-gated **Admin Account** section
  on `sql/index.php` (create the first admin, or reset an existing admin's
  password) — see "How `sql/index.php` works" above.

**Prompt 3 — Attendance:**
- `attendance` table + seeded `attendance_grace_minutes` setting.
- Staff-side check-in/check-out (`/staff/attendance.php`) with office-WiFi
  IP verification, WFH fallback, unverified-location warning, holiday
  skip, and computed `present`/`late`/`half_day` status — see
  "Attendance" above for the exact rules.
- Admin attendance monitor (`/admin/attendance/`): today/date view with
  department/work_mode filters and late/unverified row highlighting,
  per-staff history, and a manual add/edit tool that logs who changed
  what and when. Linked from `admin/staff/view.php`.
- `cron/mark-absent.php` — daily absent-marker for the previous day,
  holiday-aware, idempotent, runnable via CLI or a keyed HTTP request.
- Verified end-to-end against a live MariaDB instance: schema auto-create,
  IP-match / WFH / unverified check-in paths, on-time vs. late grace-period
  boundaries, half-day detection on early checkout, duplicate check-in/out
  rejection, the DB-level unique constraint, holiday skip (including for a
  staff member who already checked in before the holiday was added),
  admin monitor filters and row highlighting, manual add/edit (including
  upsert-not-duplicate and notes accumulation), and the cron script's
  idempotency, holiday skip, and HTTP key gate (wrong key rejected).

WFH-request-driven attendance (auto-treating an approved WFH request day
as verified `wfh` for hybrid/office staff, instead of `unverified`) is
**deferred to Prompt 4**, once WFH requests exist. Leave/WFH requests and
payout are **not built yet** (Prompts 4-5).

## What's planned (not yet built)

- **Prompt 4:** Leave & WFH requests (will likely use/extend `holidays` +
  the commented-out `holiday_staff` stub, and wire WFH-request-driven
  attendance per the note above).
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
