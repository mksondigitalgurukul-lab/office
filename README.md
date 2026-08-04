# Digital Ali Pro OMS

Single-tenant Office Management System (staff attendance, leave, and
payout) for one company, built as plain PHP + MySQL for cPanel shared
hosting. Deployed at
[www.digitalalipro.in/office](https://www.digitalalipro.in/office).

> **V1 is complete, and Prompt 6 has layered a full UI/UX redesign on
> top.** Project skeleton, admin/staff auth, staff management, attendance
> check-in/out with office-WiFi verification, leave/WFH requests, salary
> management, monthly payout generation, leave-types CRUD, and attendance
> reports (Prompts 1-5) — now wrapped in a colorful sidebar-based design
> system with dark/light mode across every page (Prompt 6, frontend-only —
> no business logic or schema changed). See `CLAUDE.md` for full
> technical detail, the full design system writeup, and V2 ideas.

## Requirements

- PHP 8.0+ with the `pdo_mysql` extension (standard on cPanel). If a page
  throws `Call to undefined function str_ends_with()` (or similar) your
  domain's PHP version is older than this — check cPanel's **MultiPHP
  Manager** and bump it. Recommended, since older PHP is end-of-life and
  unpatched — the codebase itself no longer relies on any 8.0+-only
  function, so it also runs on 7.x, but that's a fallback, not a target.
- MySQL / MariaDB database.
- A cPanel hosting account with the ability to create a MySQL database and
  user (or an equivalent MySQL server for local development).

## Deploying to cPanel (`/office`)

1. **Upload the files.** Upload the entire contents of this repository into
   the `office` folder under your domain's document root, so the app is
   reachable at `https://www.digitalalipro.in/office`.
2. **Create a database.** In cPanel → *MySQL Databases*, create a database
   and a database user, and grant that user all privileges on the database.
3. **Create `config.php` from the template.** `config.php` itself is not
   tracked in git (so a future `git pull` on the server never overwrites
   your live credentials) — copy the template and edit the copy:
   ```bash
   cp config-example.php config.php
   ```
   Then fill in the real values in `config.php`:
   ```php
   define('DB_HOST', 'localhost');
   define('DB_NAME', 'your_database_name');
   define('DB_USER', 'your_database_user');
   define('DB_PASS', 'your_database_password');
   ```
   Never commit `config.php` itself back into git.
4. **Create the schema.** Visit `https://www.digitalalipro.in/office/sql/index.php`
   in a browser. On a brand-new install (no admin account yet) this page is
   open in **setup mode** — it will automatically create the `admins`,
   `settings`, `office_locations`, `holidays`, `staff`,
   `staff_work_time_history`, `attendance`, `leave_types` (seeded with
   Sick/Casual/Paid/Unpaid), `leave_requests`, `wfh_requests`,
   `staff_salary`, `payouts`, and `attendance_sessions` tables and show
   you their structure.
   **Seven files need a manual step**: `011_attendance_add_on_leave_status.sql`,
   `014_leave_types_add_is_active.sql`, `017_staff_salary_add_reason.sql`,
   `018_payouts_add_forgiveness.sql`, `019_seed_absent_sync_baseline.sql`,
   and `020_lunch_breaks.sql` are `ALTER TABLE`s or data seeds, and
   `015_delhi_holidays_2026_2027.sql` is a data seed — none have a
   `CREATE TABLE`, so none auto-run. Find each in the list and click its
   **Re-run** button once. Without the first, the absent-sync (step 12
   below) can't record the `'on_leave'` attendance status; without the
   second, leave-types management (step 13) won't work; the third seeds a
   starter Delhi/India holiday calendar (see step 11 below) — skip it if
   you'd rather add your own holidays from scratch; without the fourth,
   setting a staff member's salary will error (the reason dropdown needs
   its column); without the fifth, "Forgive Deduction" on a payout will
   error; without the sixth, the absent-sync (step 12) won't run at all;
   without the seventh, the staff-side "Lunch Start" button will error
   (it needs `attendance_sessions.break_type`) and the lunch-duration
   warning threshold setting won't exist.
5. **Create the first admin.** Two options — either works:
   - **Via DB Tools:** on `https://www.digitalalipro.in/office/sql/index.php`
     (still in setup mode), scroll to **Admin Account** and fill in the
     "Create Admin" form: name, email, password, and a **management key**
     you choose yourself. Remember that key — it's saved to `sql/key.txt`
     on the server and will be required later to reset any admin's
     password from that same section.
   - **Via `create-admin.php`:** visit
     `https://www.digitalalipro.in/office/create-admin.php` and fill in a
     name, email, and password (8+ characters). This can only be run once —
     it refuses to run again as soon as one admin account exists. Or over
     SSH: `php create-admin.php "Your Name" you@example.com "a-strong-password"`.
6. **Secure `create-admin.php`.** After creating the first admin, delete
   `create-admin.php` from the server (or block access to it in
   `.htaccess`) — it's a one-time setup script and shouldn't stay reachable.
7. **Log in.** Go to `https://www.digitalalipro.in/office/admin/login.php`
   and sign in with the admin account you just created. You'll land on the
   dashboard. From there, **DB Tools** in the nav (`/sql/index.php`) is now
   locked behind login, as normal — use it going forward for any future
   schema changes shipped in later prompts.
8. **Add staff accounts.** From the admin dashboard, go to **Staff** →
   **+ Add Staff**. Fill in the employee's details and either set an
   initial password or leave it blank to auto-generate one — a
   generated password is shown once right after creation, so save it
   immediately (there's no way to retrieve it again; use the staff
   member's own password-change form on their dashboard if it's lost).
   Staff then log in separately at
   `https://www.digitalalipro.in/office/staff/login.php` with that email
   and password.
9. **Resetting an admin's password later.** Log in and go to **DB Tools**
   (`/sql/index.php`) → **Admin Account**. Once at least one admin exists,
   this section shows an "Update Password" form instead of "Create Admin" —
   pick the admin, set a new password, and enter the management key from
   step 5. If you've lost that key, edit `sql/key.txt` directly on the
   server (via cPanel File Manager or SSH) to set a new one.
10. **Register office WiFi IPs.** From the admin dashboard, go to
    **Office Locations** → **+ Add Location**. Use the public IP your
    office WiFi shows to the internet (check `https://whatismyip.com`
    from an office machine) — this is what `staff/attendance.php`
    compares check-ins against to set `work_location = 'office_verified'`.
    Deactivate (don't delete) a location if it's no longer valid.
11. **Set up your holiday calendar.** If you clicked **Re-run** on
    `015_delhi_holidays_2026_2027.sql` in step 4, a starter set of Delhi/
    India holidays through end of 2027 is already there — open
    **Holidays** and double-check the ones flagged `"(estimate —
    verify)"` in their name against the official gazette (they're
    lunar-calendar festivals whose exact date I couldn't be fully certain
    of two years out), and delete/re-add anything that's off by a day.
    Otherwise, go to **Holidays** → add company holidays one at a time
    (date + name), and use **Generate Sundays** (enter how many months
    ahead — 12 covers a full year) to bulk-add every upcoming Sunday as a
    default day off, instead of adding 52 rows by hand. Any holiday fully
    blocks staff check-in for that day —
    *except* Sundays specifically, which show an optional "Check In
    (Extra Work)" button so someone can still log a normal attendance row
    if they choose to work that day (it doesn't automatically add
    anything to their payout — see `CLAUDE.md` → "Holidays" if you want
    to pay extra for it, that's a manual bonus). Do this before step 12
    below, since the absent-marker treats every holiday date the same
    way.
12. **Nothing to schedule — the absent-marker runs itself.** There's no
    cron job to set up. `includes/functions.php`'s `syncAbsences()` runs
    automatically on every admin page load (wired into
    `includes/admin-header.php`) and catches up on marking
    `'absent'`/`'on_leave'` for any past day nobody's visited since —
    skipping holidays, and aware of approved leave/WFH (see `CLAUDE.md` →
    "Attendance" and "Leave & WFH requests" for the exact rules).
    Requires step 4's `011_attendance_add_on_leave_status.sql` and
    `019_seed_absent_sync_baseline.sql` Re-runs to have been done, or it
    won't do anything. You'll see a green "Attendance sync: marked N
    absent..." banner at the top of the page whenever it actually catches
    something up; most page loads it does nothing (already caught up) and
    shows no banner. It backfills at most the last 90 days on any single
    visit — if nobody logs in for longer than that, older gaps are
    permanently left unmarked rather than triggering a huge one-time
    backfill. **Consequence to know:** if you generate a payout for a
    month before any admin has visited a page since that period ended,
    the payout will undercount absences (those days simply have no
    attendance row yet) — use **Regenerate** on that payout after the
    sync has caught up to get accurate figures.
13. **Review leave types (optional).** Sick/Casual/Paid/Unpaid are seeded
    automatically. Go to **Leave Types** to add more, rename one, toggle
    whether it's paid, or deactivate one you don't use — deactivating
    hides it from the staff request form without touching past requests.
14. **Set staff salaries — required before generating any payout.** On
    each staff member's profile (**Staff** → pick a staff member), scroll
    to **Salary** → **Set / Change Salary**, enter their monthly salary,
    an effective-from date, and a **reason** — pick a preset from the
    dropdown (Annual Increment, Promotion, etc.) or choose "Other" to
    type a short reason. Like work timing, this is append-only — changing
    it later adds a new row, it never edits history. A staff member with
    no salary set is silently skipped when you generate a payout for them
    (and told so in the result message).
15. **Generate a monthly payout.** Go to **Payout** → **Generate Payout**,
    pick a month and either all active staff or one, and submit. This
    creates/updates **draft** payouts using that month's attendance and
    approved-leave data — see `CLAUDE.md` → "Payout" for the exact
    formula. From a draft's detail page you can adjust the **bonus**
    (recomputes the net payout live), or **Forgive Deduction** to waive
    the unpaid deduction entirely (the payslip then shows it struck
    through with a highlighted "Forgiven by {you}" note; **Un-forgive**
    reverts it) — then **Finalize** it. Finalized (and later **paid**)
    payouts are never silently overwritten by a later "Generate"; use
    that same payout's **Regenerate** button if you need to recalculate
    one after attendance/leave data changed (it resets to draft and keeps
    both the bonus and any forgiveness). **Mark as Paid** on a finalized
    payout stamps `paid_at`. Each payout's detail page is also a
    print-friendly payslip — use the **Print / Save as PDF** button (a
    real PDF export wasn't built; the browser's print-to-PDF covers it).
16. **Run attendance reports.** Go to **Reports**, pick a staff member
    (or "All active staff") and a range (last 7 days / this month / this
    year / a custom from-to), then **Export CSV** if you want the same
    table as a file. Picking one staff member also shows their
    day-by-day attendance log for the range, and CSV-exports that log
    instead of the summary.

## Local development

1. Copy `config-example.php` to `config.php` (`cp config-example.php
   config.php`) and point it at a local MySQL/MariaDB database, using
   `127.0.0.1` or `localhost`.
2. Run PHP's built-in server from the project root:
   ```bash
   php -S localhost:8000
   ```
3. Visit `http://localhost:8000/sql/index.php` to create the schema, then
   click **Re-run** on `011_attendance_add_on_leave_status.sql`,
   `014_leave_types_add_is_active.sql`, and
   `019_seed_absent_sync_baseline.sql` (see step 4 above), then visit
   `http://localhost:8000/create-admin.php` to create your first admin.
4. Log in at `http://localhost:8000/admin/login.php`, add a staff member
   under **Staff**, then log in as them at
   `http://localhost:8000/staff/login.php`.
5. To test office-WiFi verification locally, add your machine's IP as an
   `office_locations` row via **Office Locations** (when using PHP's
   built-in server from `localhost`, that's usually `127.0.0.1`).
6. Visit any admin page (e.g. reload the dashboard) to trigger
   `syncAbsences()` and test the absent-marker — no cron job needed, it
   runs automatically on page load (requires the `011` and `019`
   migrations from step 3 above).
7. Set a salary under that staff member's profile, then generate a
   payout for them under **Payout** to test the calculation.

## Folder overview

```
/office
  /admin           Admin panel pages (login, logout, dashboard, staff
                    management, attendance monitor, leave/WFH review,
                    leave-types CRUD, holidays CRUD, office-location CRUD,
                    payout, reports, settings)
    /staff          Staff CRUD + work-timing override tool + salary tool
    /attendance      Today/date monitor, per-staff history, manual override
    /leave           Leave request list/filter + approve/reject
    /wfh             WFH request list/filter + approve/reject + direct assignment
    /leave-types     leave_types CRUD (add/rename/paid toggle/active toggle)
    /holidays        holidays CRUD (add/delete one) + bulk "Generate Sundays"
    /office-locations  Office WiFi IP CRUD (add/edit/active toggle)
    /payout          Generate/list/view payouts — draft/finalize/paid, printable payslip
    /reports         attendance.php — flexible attendance summary + CSV export
    settings.php     Edit every settings row via one dynamic form
  /staff            Staff-facing pages: login, logout, dashboard, attendance
                    (multiple check-ins/day + lunch start/over supported),
                    calendar (week/month/year holiday+leave+attendance
                    view), work-report (own monthly session-by-session
                    breakdown), leave, wfh, payout (read-only own history),
                    profile (their own session, separate from /admin)
  /assets/css      Design system stylesheet — CSS-variable light/dark
                   theming, sidebar shell, cards/forms/badges/tables
  /assets/js       Theme toggle + persistence, mobile sidebar drawer,
                   confirm-dialog behavior
  /includes        db.php (PDO connection), auth.php (admin session
                   helpers), staff_auth.php (staff session helpers),
                   functions.php (escaping + settings + work-timing +
                   attendance + leave/WFH + payout helpers + badgeVariant()
                   status-color helper + syncAbsences(), the no-cron
                   absent-marker), admin-header.php/admin-footer.php
                   and staff-header.php/staff-footer.php (shared sidebar
                   chrome every page requires — syncAbsences() runs from
                   admin-header.php on every admin page — see CLAUDE.md
                   "Design System" and "Attendance" for the full details)
  /sql             Numbered schema files (001_admins.sql, ...) + index.php
                   (the schema runner / DB dashboard / ad-hoc SQL tool /
                   Admin Account section — see CLAUDE.md for how it works),
                   plus .htaccess and a gitignored key.txt (admin
                   management key, created on first use)
  config-example.php  Tracked config template — copy to config.php and edit
  config.php       DB credentials (gitignored — never committed)
  index.php        Redirects to /admin/login.php
  create-admin.php One-time first-admin creation script
  CLAUDE.md        Detailed technical/architecture notes for this project — the
                   source of truth for V2 planning, see its "V2 ideas" section
  README.md        This file
```

## Schema changes going forward

**Do not edit the database by hand in phpMyAdmin.** Add a new numbered
`.sql` file to `/sql` (e.g. `015_description.sql`), then visit
`/sql/index.php` while logged in — it detects and runs new files
automatically, and lets you re-run or apply ad-hoc `ALTER` statements
safely. See `CLAUDE.md` for the full convention.

## Settings keys

Editable via **Settings** in the admin nav (`admin/settings.php`) — a
dynamic form over every row in the `settings` table. (DB Tools → Ad-hoc
SQL still works too, for anything not covered by that form.)

| Key | Meaning | Default |
|---|---|---|
| `company_name` | Displayed app name | `Digital Ali Pro OMS` |
| `default_work_start_time` / `default_work_end_time` | Universal work hours, used when a staff member has no timing override | `09:30` / `18:30` |
| `timezone` | Informational | `Asia/Kolkata` |
| `attendance_grace_minutes` | Minutes after `default_work_start_time` (or a staff member's override start time) before a check-in counts as `'late'` | `15` |
| `lunch_warning_minutes` | Minutes a lunch break can run before "Lunch Over" shows an over-limit warning (informational only, never blocks) | `60` |

## Holidays

Managed via **Holidays** in the admin nav — add a one-off holiday
(date + name), delete one, or bulk-add every upcoming Sunday with
**Generate Sundays** (pick how many months ahead; re-running it is safe,
it never overwrites an existing holiday on a date that already has one).
Any holiday blocks staff check-in for that day, *except* Sundays — those
show an extra "Check In (Extra Work)" option so someone can still log
attendance if they choose to work, without affecting payout automatically
(add a manual bonus on that payout if you want to pay for it). See
`CLAUDE.md` → "Holidays" for the full rule.

A starter Delhi/India holiday calendar (through end of 2027) can be
seeded via `sql/015_delhi_holidays_2026_2027.sql` — see step 4/11 above.
Fixed-date holidays in it are certain; movable festival dates are
best-estimates flagged in their name, worth double-checking against the
official gazette.

Staff can browse the full holiday calendar (plus their own leave/WFH and
past attendance) at **Calendar** in their nav — week, month, or year
view, with Previous/Today/Next navigation.

## Multiple check-ins & work report

Staff aren't limited to one check-in/check-out per day — after checking
out, **Attendance** shows a "Check In Again" option (requires a short
reason, e.g. "plan changed, resuming work") that starts a new session for
the same day. Every session for today is listed on that page; late-day
half-day detection is based on the **total** worked time across all of
them, not just the last one.

**Work Report** in the staff nav shows a month-by-month, day-by-day
breakdown of every session, total worked time, and a plain-language
label (Below Target / On Target / Great Work / Excellent Work) comparing
worked time to that day's scheduled hours — purely informational, it
never affects payout.

## Lunch breaks

Built on top of the multi-session mechanism above — a lunch break is just
a labeled session close/reopen pair, so worked-hours totals already
exclude lunch time automatically. On **Attendance**, while checked in a
staff member sees a **Lunch Start** button (once per day only); while on
a lunch break they see **Lunch Over** (resumes the session) and **Check
Out (end day)** (ends the day right from the break, no need to resume
first). If a break runs past `lunch_warning_minutes` (default 60), Lunch
Over shows a warning — informational, never blocking. Every lunch action
is stamped into the day's notes, so it's visible in the admin attendance
monitor with no separate UI needed there. See `CLAUDE.md` → "Attendance"
→ Lunch breaks for the full write-up, including the known limitation that
**Calendar**'s worked-hours column (unlike **Work Report**) doesn't yet
exclude lunch time.

## Leave types

Seeded with Sick, Casual, Paid (all `is_paid = 1`), and Unpaid
(`is_paid = 0`). Managed via **Leave Types** in the admin nav (add,
inline rename, toggle paid/unpaid, deactivate) — see step 13 above.
`is_paid` matters beyond labeling: it's what `admin/payout/generate.php`
uses to decide whether an approved leave request reduces a payout.

## Roadmap — V1 complete

- **Prompt 1:** Foundation — skeleton, schema runner, admin login. ✅
- **Prompt 2:** Staff management, staff login, work-time overrides with
  history. ✅
- **Prompt 3:** Daily attendance check-in/check-out with office-WiFi
  verification, admin attendance monitor, absent-marking cron. ✅
- **Prompt 4:** Leave requests, WFH requests (staff- and admin-initiated),
  office-location CRUD, WFH wired into attendance. ✅
- **Prompt 5:** Salary management, monthly payout generation
  (draft → finalize → paid, printable payslip), leave-types CRUD,
  flexible attendance reports with CSV export. ✅
- **Prompt 6 (this build):** Full UI/UX redesign — colorful sidebar-based
  design system with dark/light mode across every admin and staff page,
  `admin/settings.php` and `staff/profile.php` built to close out dead
  nav links, frontend-only (no business logic or schema changes). ✅

All six prompts are done and verified end-to-end against real
attendance/leave/payout data. See `CLAUDE.md`'s "Design System" section
for the full color palette and layout conventions, and its "What's
planned — V2 ideas" section for recommended next steps.
