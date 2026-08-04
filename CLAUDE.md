# CLAUDE.md — Digital Ali Pro OMS

Guidance for Claude (or any future contributor) working in this repository.

## Project

**Digital Ali Pro OMS** is a single-tenant Office Management System for one
company's own staff (not multi-company / multi-tenant). It runs on plain PHP
(no framework) + MySQL over PDO, with session-based auth, deployed to cPanel
shared hosting at `https://www.digitalalipro.in/office`.

This is a **multi-prompt build. V1 is now complete (Prompts 1-5/5).**
Foundation (1), staff management (2), attendance (3), leave/WFH requests
(4), and now salary management, monthly payout generation, leave-types
CRUD, and attendance reports (5). **This file is the source of truth for
any V2 planning** — see "What's built" and the V2 recommendations at the
bottom for where to pick up next.

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
      view.php        Staff profile: work timing + salary (current + full history of each) +
                      link to attendance history + link to this staff member's payouts
      set-timing.php  Insert a new staff_work_time_history row (default or custom hours)
      set-salary.php  Insert a new staff_salary row (append-only, same pattern as timing)
    /attendance        Attendance monitor (login-protected, admin session)
      index.php       Today/date view — filter by department/work_mode, highlights late/unverified rows
      staff.php       Per-staff attendance history (last 60 records)
      edit.php        Manual add/edit of one staff's attendance row for one date (logs the edit in notes)
    /leave              Leave request review (login-protected, admin session)
      index.php       List/filter (status/staff/date range) all leave requests; one-click approve/reject
    /wfh                WFH request review + direct assignment (login-protected, admin session)
      index.php       List/filter WFH requests, approve/reject staff-initiated ones, and a
                      form to directly assign+auto-approve a WFH day for any staff member
    /leave-types        CRUD for leave_types (login-protected, admin session)
      index.php       List with inline rename, is_paid toggle, active/inactive toggle, add form
    /office-locations   CRUD for office WiFi IPs (login-protected, admin session)
      index.php       List + active/inactive toggle
      add.php         Create a location
      edit.php        Edit name/IP/active flag
    /payout             Monthly payout generation + review (login-protected, admin session)
      generate.php    Pick a month (+ optional single staff), calculate draft payouts —
                      see "Payout" below for the exact calculation
      index.php       List payouts — filter by month/status/staff, shows column totals
      view.php        Full breakdown, printable payslip, bonus edit (draft only),
                      finalize / mark-as-paid / regenerate actions
    /reports            Reporting (login-protected, admin session)
      attendance.php  Flexible attendance summary (7 days/month/year/custom, all-staff or
                      one staff with a day-by-day log) + CSV export — see "Reports" below
  /staff              Staff-facing pages (staff session, separate from admin)
    login.php         Email + password login for staff accounts
    logout.php         Destroys staff session, redirects to login
    dashboard.php      Welcome + work mode/timing + change-password form + "Upcoming" list
                       (holidays + this staff member's own approved leave/WFH) + nav stub
    attendance.php      Today's check-in/check-out status + buttons; holiday-skip
    leave.php            Submit a leave request, see own history + a simple per-type balance
    wfh.php              Submit a WFH request for one date, see own history
  /assets
    /css/style.css     Shared styles for all pages
    /js/main.js         Small shared UI behaviors (confirm dialogs)
  /includes
    db.php              PDO connection (getDB())
    auth.php            Admin session helpers: requireLogin(), loginAdmin(), logoutAdmin(), currentAdmin()
    staff_auth.php       Staff session helpers: requireStaffLogin(), loginStaff(), logoutStaff(), currentStaff()
    functions.php       h(), getSetting()/setSetting(), getCurrentWorkTiming(), the attendance
                        helpers (getClientIp(), isOfficeIp(), etc.), hasApprovedWfh() /
                        hasApprovedLeave() (see "Leave & WFH requests" below), and the payout
                        helpers getCurrentSalary() / computePayoutFigures() / daysInMonth() /
                        getUnpaidLeaveDaysInMonth() / monthBounds() — see "Payout" below
  /sql
    001_admins.sql       Schema file — admins table
    002_settings.sql     Schema file — settings table + seed rows
    003_office_locations.sql   Schema file — office_locations table
    004_holidays.sql     Schema file — holidays table (+ commented holiday_staff stub)
    005_staff.sql         Schema file — staff table
    006_staff_work_time_history.sql   Schema file — staff_work_time_history table
    007_attendance.sql    Schema file — attendance table + seeds attendance_grace_minutes setting
    008_leave_types.sql    Schema file — leave_types table + seeds Sick/Casual/Paid/Unpaid
    009_leave_requests.sql Schema file — leave_requests table
    010_wfh_requests.sql   Schema file — wfh_requests table
    011_attendance_add_on_leave_status.sql   ALTER — adds 'on_leave' to attendance.status
                                             (no CREATE TABLE, so it does NOT auto-run —
                                             click "Re-run" for it once on /sql/index.php)
    012_staff_salary.sql   Schema file — staff_salary table (append-only, same pattern
                           as staff_work_time_history)
    013_payouts.sql        Schema file — payouts table
    014_leave_types_add_is_active.sql   ALTER — adds is_active to leave_types (also does
                                        NOT auto-run — click "Re-run" for it once, like 011)
    index.php            Schema runner + DB dashboard + Admin Account section (see below)
    .htaccess             Blocks direct HTTP access to *.txt files (key.txt, schema_log.txt)
    key.txt               Admin management key — gitignored, created by the Admin Account section
  /cron
    mark-absent.php       Daily script: marks active staff with no attendance row for
                          yesterday as 'absent' — now leave/WFH-aware, see "Attendance" below
  config-example.php     Tracked config template (DB credentials + CRON_SECRET placeholders)
  config.php            Copied from config-example.php on deploy — gitignored, never committed
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
  `config-example.php` is committed, holding placeholder values. On the
  real server (or for local dev), copy it (`cp config-example.php
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
| `leave_types` | Kinds of leave (`is_paid` flag, `is_active` flag added in `014_...sql`). Seeded with Sick, Casual, Paid (all paid) and Unpaid. Managed via `admin/leave-types/` — deactivated (never hard-deleted) types stay visible in historical data but drop out of the staff-side request dropdown. |
| `leave_requests` | Staff-submitted leave requests (always `requested_by` = the staff member themself — no admin-initiated leave). `days_count` is a simple inclusive calendar-day count, no accrual rules. `status` starts `pending`; an admin sets `approved`/`rejected` plus `reviewed_by`/`reviewed_at`. |
| `wfh_requests` | One request per staff member per day (unique on `staff_id` + `wfh_date`). Either staff-submitted (`created_by_type = 'staff'`, starts `pending`) or admin-assigned (`created_by_type = 'admin'`, `status` is `approved` immediately, `reviewed_by` stays `NULL`). See "Leave & WFH requests" below. |
| `staff_salary` | Append-only log of every salary change for a staff member — identical pattern to `staff_work_time_history` (see "Work timing history convention" above), but with **no universal-default fallback**: a staff member with zero rows simply has no resolvable salary. `set_by` references the admin who recorded the change. |
| `payouts` | One row per staff member per calendar month (unique on `staff_id` + `month`, stored `'YYYY-MM'`). Generated by `admin/payout/generate.php`; see "Payout" below for the full calculation and the `draft` → `finalized` → `paid` workflow. |

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
  - No match, `staff.work_mode` is `'office'` or `'hybrid'`, but
    `hasApprovedWfh($staffId, $today)` is true → `wfh` (no warning) —
    an approved WFH request for today overrides the usual unverified
    flag for office/hybrid staff. Wired in `staff/attendance.php`.
  - Otherwise (no match, no approved WFH for today) → `unverified`, with
    an on-page warning that the check-in is flagged for admin review —
    but it still saves; nothing blocks it.
  - `office_manual` is only ever set by an admin's manual override
    (`admin/attendance/edit.php`), never by the staff-side check-in flow.
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
  - `'absent'` and `'on_leave'` are only ever set by `cron/mark-absent.php`
    or by an admin's manual override — never by the check-in/out flow
    itself. `'on_leave'` requires the `011_attendance_add_on_leave_status.sql`
    migration to have been (manually) run — see the `/sql` listing above.
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
  scheduling) and, for **yesterday's** date only, considers every active
  staff member with no attendance row for that date (skipped entirely if
  yesterday was a company-wide holiday):
  - Approved leave covering that date (`hasApprovedLeave()`) → inserts an
    `'on_leave'` row instead of `'absent'` (a deliberate choice — see
    "Leave & WFH requests" below for why leave and WFH are treated
    differently here).
  - Approved WFH for that date, no check-in (`hasApprovedWfh()`) → **no
    row is inserted at all**, same treatment as a holiday.
  - Otherwise → `'absent'` row (`work_location = 'unverified'`), as before.
  Idempotent: safe to re-run, existing rows are left untouched. Runs via
  CLI with no auth; if triggered over HTTP instead (e.g. a cPanel "URL"
  cron job) it requires `?key=` to match `CRON_SECRET` in `config.php`
  (placeholder in `config-example.php` — change it on the real server's
  `config.php`).

## Leave & WFH requests

- **Leave** (`leave_requests`, `staff/leave.php`, `admin/leave/index.php`)
  is always staff-initiated — `requested_by` is always the requesting
  staff member's own id, there's no admin-initiated leave. `days_count` is
  a simple inclusive calendar-day count (`to_date - from_date + 1`) — no
  weekend/holiday exclusion or accrual rules. The staff-side "leave
  balance" is just `SUM(days_count)` of that staff member's `approved`
  requests this calendar year, grouped by `leave_type_id` — not a real
  accrual ledger.
- **WFH** (`wfh_requests`, `staff/wfh.php`, `admin/wfh/index.php`) has two
  distinct origins, both writing the same table:
  - **Staff-initiated:** `created_by_type = 'staff'`, `created_by_id` =
    the staff member's own id, starts `status = 'pending'`, needs an
    admin's approve/reject (which sets `reviewed_by`/`reviewed_at`).
  - **Admin-initiated:** `created_by_type = 'admin'`, `created_by_id` =
    the admin's id, `status = 'approved'` **immediately** — an admin
    assigning WFH doesn't review/approve their own assignment, so
    `reviewed_by`/`reviewed_at` stay `NULL` for these rows. Built as
    "Assign a WFH Day" on `admin/wfh/index.php`.
  - `created_by_id` has **no foreign key** — it's a polymorphic reference
    (staff.id when `created_by_type = 'staff'`, admins.id when `'admin'`).
  - Unique on (`staff_id`, `wfh_date`): at most one request per staff per
    day. Both the staff submission form and the admin assignment form
    write via `INSERT ... ON DUPLICATE KEY UPDATE` keyed on that
    constraint — a staff member resubmitting after rejection, or an admin
    re-assigning, updates the same row (and resets `reviewed_by`/
    `reviewed_at` to `NULL`) rather than creating a duplicate. The
    staff-side form additionally blocks resubmission with an error while
    an existing request for that date is still `pending` or `approved`
    (only a `rejected` one can be resubmitted).
- **Why leave and WFH are treated differently in `cron/mark-absent.php`:**
  approved leave means the staff member isn't expected to work that day at
  all, so it gets its own `'on_leave'` attendance status — a clear signal
  for future reports. Approved WFH means they *were* expected to work
  (just remotely) — if they never checked in, that's a missed check-in
  worth noticing, not a day off, so the cron doesn't fabricate a `'wfh'`
  attendance row for them; it simply doesn't penalize them with `'absent'`
  either. This was a judgment call within what the prompt allowed ("use
  your judgment... document the choice clearly") — revisit if reporting
  needs change.
- `hasApprovedLeave(int $staffId, string $date): bool` and
  `hasApprovedWfh(int $staffId, string $date): bool` in
  `includes/functions.php` are the single source of truth for "was this
  staff member on approved leave/WFH on this date" — reuse them (used by
  both `staff/attendance.php`'s check-in and `cron/mark-absent.php`).
- **Leave types** (`admin/leave-types/index.php`) are never hard-deleted —
  `is_active` (added by `014_leave_types_add_is_active.sql`) soft-deletes
  a type instead, since existing `leave_requests` may reference it.
  `staff/leave.php`'s request dropdown only offers `is_active = 1` types;
  the staff-side leave-balance table still shows all types (including
  inactive ones) for historical completeness. `is_paid` is toggleable at
  any time — changing it only affects **future** payout generations, past
  `payouts` rows already baked their deduction in and are not retroactively
  changed.

## Payout

Monthly payouts (`payouts`, one row per staff per `'YYYY-MM'` month) are
generated by `admin/payout/generate.php`, reviewed/adjusted at
`admin/payout/view.php`, and listed at `admin/payout/index.php`. The
calculation lives in `includes/functions.php` as
`computePayoutFigures(int $staffId, string $month): ?array`.

- **Salary** (`staff_salary`) is append-only, resolved via
  `getCurrentSalary(int $staffId, ?string $onDate = null): array` —
  identical pattern to `getCurrentWorkTiming()`, but returns
  `['amount' => null, ...]` (not a universal default) if the staff member
  has no `staff_salary` row yet. A payout can't be generated for a staff
  member with no resolvable salary — `generate.php` skips them and says so.
- **Which date resolves "current" salary for a payout:** the **last day**
  of the payout's month, not generation day or the month's first day. This
  is a deliberate simplification consistent with how `getCurrentWorkTiming()`
  is used elsewhere in this codebase — there is no proration for a salary
  change effective *mid*-month; whatever's in effect on the month's last
  day applies to the whole month. Revisit if that's not accurate enough.
- **Day counts** (`present_days`, `absent_days`, `on_leave_days`,
  `wfh_days`, `half_days`) come straight from `attendance` rows in the
  target month:
  - `present_days` = `status IN ('present', 'late')` — late is folded in;
    there's no separate `late_days` payout column (lateness affects the
    `admin/attendance/` monitor and `admin/reports/`, not pay).
  - `wfh_days` = `work_location = 'wfh'`, counted **in addition to**
    (not instead of) whatever `present_days`/`half_days` bucket that same
    row also falls into — it's an informational overlay, not a disjoint
    category, and never affects the deduction math.
  - `absent_days` / `on_leave_days` / `half_days` are their respective
    `status` counts.
- **`unpaid_deduction` is NOT derived from `on_leave_days`.** Per the
  brief's explicit instruction, unpaid-leave days come straight from
  `leave_requests` JOINed to `leave_types.is_paid = 0` via
  `getUnpaidLeaveDaysInMonth()`, which sums only the portion of each
  approved+unpaid request that overlaps the target month (a request
  spanning a month boundary is split correctly). This is deliberately
  independent of whatever's actually landed in `attendance` — reliable
  even if `cron/mark-absent.php` hasn't caught up on the last day or two
  of the month yet. The `on_leave_days` **column** on `payouts` is a
  separate, purely informational count of `attendance.status = 'on_leave'`
  rows — it can include *paid* leave too, and isn't part of the deduction.
- **Formula:**
  `per_day_rate = base_salary / days_in_month`
  `deduction_days = absent_days + unpaid_leave_days + (half_days × 0.5)`
  `unpaid_deduction = round(per_day_rate × deduction_days, 2)`
  `net_payout = base_salary − unpaid_deduction + bonus`
  `days_in_month` is the target month's actual calendar length (28-31),
  via `daysInMonth()`.
- **`draft` → `finalized` → `paid` workflow:**
  - `generate.php` always writes `status = 'draft'` — for a brand-new
    (staff, month) pair, or upserting an *existing* `draft` row (bonus is
    **carried forward**, not reset to 0, on a re-generate-while-still-draft).
    If a `payouts` row for that (staff, month) already exists with status
    `finalized` or `paid`, that staff is **skipped** — `generate.php`
    never silently overwrites a locked payout, bulk or otherwise.
  - From `draft`, the bonus is editable (`view.php`, recomputes
    `net_payout` immediately) and "Finalize" locks it to `finalized`.
  - From `finalized`, "Mark as Paid" sets `status = 'paid'` and stamps
    `paid_at`.
  - **"Regenerate"** (`view.php`, any status) is the explicit override the
    brief calls for: recomputes every attendance/salary-derived field
    fresh via `computePayoutFigures()`, **keeps the existing bonus**
    (an admin decision, not a derived figure), resets `status` back to
    `'draft'`, and clears `paid_at`. This is the only way to update a
    `finalized`/`paid` payout — always a single explicit per-payout action,
    never bulk.
- **Payslip view** (`admin/payout/view.php`) is styled for printing —
  `.no-print` (see `assets/css/style.css`'s `@media print` block) hides
  the nav/topbar/action buttons, leaving just the payslip card. "Print /
  Save as PDF" calls `window.print()`; there's no server-side PDF
  generation (no library available/needed — the browser's print-to-PDF
  covers V1's "printable, PDF export optional" requirement).

## Reports

`admin/reports/attendance.php` is the one report in V1: a flexible
attendance summary, filterable by staff (all active staff, or one) and
date range (`range=7days|month|year|custom`, with explicit `from`/`to`
for `custom`). All-staff mode shows one summary row per staff; picking a
single staff additionally shows their day-by-day log for the range.
`&format=csv` exports the **currently-displayed** table — the summary
for all-staff mode, or the day-by-day log for single-staff mode — via
`fputcsv()` with `Content-Disposition: attachment`. No caching/precompute;
every request re-aggregates `attendance` directly with `SUM(condition)`
(MySQL/MariaDB evaluate a boolean expression as 1/0), so this only stays
fast at V1's expected data volumes — revisit if `attendance` grows large.

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

## What's built — V1 complete (Prompts 1-5/5)

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

**Prompt 4 — Leave & WFH requests:**
- `leave_types` (seeded Sick/Casual/Paid/Unpaid), `leave_requests`,
  `wfh_requests` tables, plus a migration adding `'on_leave'` to
  `attendance.status` (manual Re-run required — see the `/sql` listing).
- Staff-side leave (`/staff/leave.php`): submit, own history, simple
  per-type approved-days-this-year balance.
- Staff-side WFH (`/staff/wfh.php`): submit (upsert-based, blocks
  resubmission while pending/approved, allows it after rejection), own
  history.
- Staff dashboard (`/staff/dashboard.php`) gained an "Upcoming" list:
  holidays + this staff member's own approved leave/WFH, soonest first.
- Admin leave review (`/admin/leave/`): filter by status/staff/date range,
  one-click approve/reject.
- Admin WFH review + assignment (`/admin/wfh/`): filter/approve/reject
  staff-initiated requests, plus "Assign a WFH Day" (auto-approved,
  `created_by_type = 'admin'`) — see "Leave & WFH requests" above for the
  full admin-vs-staff-initiated distinction.
- Admin office-locations CRUD (`/admin/office-locations/`): add, edit,
  active/inactive toggle — this was flagged as missing after Prompt 3 and
  is now built.
- Approved WFH wired into `staff/attendance.php`'s check-in logic (falls
  back to `'wfh'` instead of `'unverified'` for office/hybrid staff with
  an approved WFH request for today), and into `cron/mark-absent.php`
  (approved leave → `'on_leave'`; approved WFH with no check-in → skipped,
  not marked absent) — see "Attendance" above for the exact rules.
- Admin nav gained **Leave**, **WFH**, and **Office Locations** links
  across every admin page; staff nav split the old combined "Leave / WFH"
  stub into separate **Leave** and **WFH** links.
- Also: fixed the `config-exmaple.php` typo from Prompt 3 to
  `config-example.php` (code, `.gitignore`, and both docs updated).
- Verified end-to-end against a live MariaDB instance: schema auto-create
  for the three new tables, the `on_leave` migration via manual Re-run,
  office-locations add/edit/toggle, leave submit → approve → balance
  update, WFH submit → duplicate-blocked → approve, WFH check-in wiring
  (approved WFH staff correctly gets `'wfh'` not `'unverified'`),
  admin-assigned WFH (auto-approved, `reviewed_by` stays `NULL`), WFH
  reject → resubmit-after-rejection (upsert resets review fields), the
  staff dashboard "Upcoming" list, and the cron script's three-way split
  (absent / on_leave / skipped-for-WFH) with correct per-case attendance
  rows (or no row, for the WFH-skip case).

**Prompt 5 — Payout & Reports (completes V1):**
- `staff_salary` (append-only, same pattern as `staff_work_time_history`),
  `payouts` tables, plus a migration adding `is_active` to `leave_types`
  (manual Re-run required — see the `/sql` listing, same as Prompt 4's
  `011_...sql`).
- Salary management (`admin/staff/set-salary.php`, plus a new "Salary"
  section on `admin/staff/view.php` showing current + full history) —
  required before a payout can be generated for that staff member.
- Payout generation/review (`admin/payout/`): `generate.php` (pick a
  month + all-or-one staff, computes drafts, skips already-locked or
  salary-less staff), `index.php` (list/filter/totals), `view.php`
  (full breakdown, printable payslip, bonus edit, finalize/mark-paid/
  regenerate) — see "Payout" above for the exact calculation and workflow.
- Leave-types CRUD (`admin/leave-types/`): list, add, inline rename,
  is_paid toggle, active/inactive soft-deactivate toggle.
- Attendance reports (`admin/reports/attendance.php`): 7-day/month/year/
  custom range, all-staff summary or one staff with a day-by-day log,
  CSV export — see "Reports" above.
- Admin nav gained **Leave Types**, **Payout**, and **Reports** links
  (replacing the old stubs) across every admin page; `admin/staff/view.php`
  gained a "Payouts" link to that staff member's filtered payout list.
- Verified end-to-end against a live MariaDB instance: schema auto-create
  for the two new tables, both manual-Re-run migrations, salary set via
  the admin UI, a hand-calculated month of attendance + leave data (15
  present, 3 late, 2 half-day, 4 absent, 1 day paid leave + 2 days unpaid
  leave, 2 WFH) generating a payout whose `unpaid_deduction` and
  `net_payout` matched the expected math exactly, bonus adjustment
  recomputing `net_payout` live, the full draft→finalize→paid workflow,
  bulk generate correctly skipping an already-finalized/paid staff member
  without overwriting it, "no salary set" correctly skipped and reported,
  regenerate on a paid payout (recalculated the days, kept the bonus,
  reset to draft, cleared `paid_at`), leave-types add/rename/toggle-paid/
  deactivate (and deactivated types correctly disappearing from the
  staff-side dropdown while staying visible in the balance table), and
  the reports module's quick-select ranges, all-staff vs. single-staff
  modes, and both CSV export shapes.

## What's planned — V2 ideas

V1 (Prompts 1-5) is functionally complete end-to-end: staff onboarding,
attendance with WiFi verification, leave/WFH requests, and monthly payout
generation all work together against real data. Nothing from the V1 spec
was skipped. Recommendations for a V2, roughly in order of likely value:

- **Role-based permissions.** The `admin`/`manager` role has existed in
  the schema since Prompt 1 but is never checked anywhere — every admin
  can do everything (approve their own team's leave, see all payouts,
  edit salaries). A real permission model (e.g. managers scoped to their
  department, only `admin` role can touch payout/salary) is probably the
  single highest-value V2 item now that there's enough surface area to
  need it.
- **`holiday_staff`** — still just a commented-out stub in
  `004_holidays.sql`. Holidays are company-wide for everyone today;
  per-staff/department holiday targeting would need this built out.
- **Leave accrual.** The current "balance" is just a sum of approved
  days this calendar year — no annual entitlement, carryover, or accrual
  rate. A real leave-balance ledger (entitlement per type, carried-over
  balance, accrual over time) is a natural next step once the business
  has real policies to encode.
- **Payout salary proration.** Salary resolution for a payout uses a
  single reference date (the month's last day) — a raise or salary
  change effective mid-month is not prorated within that month. Worth
  revisiting if that precision matters.
- **office_locations IP matching is exact-string only** — no CIDR/subnet
  support. Fine for a single static office IP; would need work for offices
  with dynamic or multiple sub-ranges.
- **No email/SMS notifications** anywhere (leave approved, payout ready,
  etc.) — everything is check-the-app. Notifications would meaningfully
  improve the staff-side experience.
- **Two migrations require a manual "Re-run" click** after deploy
  (`011_attendance_add_on_leave_status.sql`,
  `014_leave_types_add_is_active.sql`) since `sql/index.php`'s auto-run
  only fires for files with a `CREATE TABLE`. Not a bug, but worth a
  glance if a V2 wants the schema runner to auto-run ALTER-only files too.
- **PDF payslip export** was explicitly optional for V1 and wasn't
  built — the payslip view is print-styled and relies on the browser's
  "print to PDF," which covers the same need without a PDF library
  dependency.
- **Reports module is attendance-only.** A payout/payroll report
  (totals across a date range, exportable) would pair naturally with the
  new `payouts` table now that it exists.

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
