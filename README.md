# Digital Ali Pro OMS

Single-tenant Office Management System (staff attendance, leave, and
payout) for one company, built as plain PHP + MySQL for cPanel shared
hosting. Deployed at
[www.digitalalipro.in/office](https://www.digitalalipro.in/office).

> This is Prompt 3 of a multi-prompt build: project skeleton, database
> schema runner, admin login, staff management + staff login with
> work-time overrides, and now daily attendance check-in/check-out with
> office-WiFi verification. Leave/WFH requests and payout are not built
> yet — see `CLAUDE.md` for the full roadmap.

## Requirements

- PHP 8.0+ with the `pdo_mysql` extension (standard on cPanel).
- MySQL / MariaDB database.
- A cPanel hosting account with the ability to create a MySQL database and
  user (or an equivalent MySQL server for local development).

## Deploying to cPanel (`/office`)

1. **Upload the files.** Upload the entire contents of this repository into
   the `office` folder under your domain's document root, so the app is
   reachable at `https://www.digitalalipro.in/office`.
2. **Create a database.** In cPanel → *MySQL Databases*, create a database
   and a database user, and grant that user all privileges on the database.
3. **Configure `config.php`.** Edit `config.php` at the project root and
   fill in the real values:
   ```php
   define('DB_HOST', 'localhost');
   define('DB_NAME', 'your_database_name');
   define('DB_USER', 'your_database_user');
   define('DB_PASS', 'your_database_password');
   ```
   Also change `CRON_SECRET` from its placeholder — it's needed if you set
   up the attendance cron job via an HTTP URL (see step 10 below).
   Do not commit your real credentials back into git.
4. **Create the schema.** Visit `https://www.digitalalipro.in/office/sql/index.php`
   in a browser. On a brand-new install (no admin account yet) this page is
   open in **setup mode** — it will automatically create the `admins`,
   `settings`, `office_locations`, `holidays`, `staff`,
   `staff_work_time_history`, and `attendance` tables and show you their
   structure.
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
10. **Register office WiFi IPs.** There's no admin page for
    `office_locations` yet (not built in any prompt so far), so add rows
    via **DB Tools** → **Ad-hoc SQL**, e.g.:
    ```sql
    INSERT INTO office_locations (location_name, ip_address, is_active)
    VALUES ('Main Office', '203.0.113.10', 1);
    ```
    Use the public IP your office WiFi shows to the internet (check
    `https://whatismyip.com` from an office machine) — this is what
    `staff/attendance.php` compares check-ins against to set
    `work_location = 'office_verified'`.
11. **Schedule the daily absent-marker.** `cron/mark-absent.php` marks
    active staff with no attendance row for *yesterday* as `'absent'`
    (skipping holidays) — see `CLAUDE.md` → "Attendance" for the exact
    rules. In cPanel → **Cron Jobs**, add one that runs shortly after
    midnight (e.g. `5 0 * * *` for 12:05 AM daily). Two ways to run it:
    - **Preferred — run the PHP file directly:**
      ```bash
      php /home/YOUR_CPANEL_USER/public_html/office/cron/mark-absent.php
      ```
    - **Fallback — if your cron only supports hitting a URL:**
      ```bash
      wget -q -O /dev/null "https://www.digitalalipro.in/office/cron/mark-absent.php?key=YOUR_CRON_SECRET"
      ```
      `YOUR_CRON_SECRET` must match `CRON_SECRET` in `config.php` (step 3)
      — without a matching key, an HTTP request to this script is
      rejected with 403. Running it via CLI/SSH cron never needs the key.
      The script is idempotent, so an accidental double-run is harmless.

## Local development

1. Point `config.php` at a local MySQL/MariaDB database (same steps as
   above, using `127.0.0.1` or `localhost`).
2. Run PHP's built-in server from the project root:
   ```bash
   php -S localhost:8000
   ```
3. Visit `http://localhost:8000/sql/index.php` to create the schema, then
   `http://localhost:8000/create-admin.php` to create your first admin.
4. Log in at `http://localhost:8000/admin/login.php`, add a staff member
   under **Staff**, then log in as them at
   `http://localhost:8000/staff/login.php`.
5. To test office-WiFi verification locally, add your machine's IP as an
   `office_locations` row via DB Tools → Ad-hoc SQL (when using PHP's
   built-in server from `localhost`, that's usually `127.0.0.1`).
6. Run `php cron/mark-absent.php` directly from the project root to test
   the absent-marker without waiting for a real cron job.

## Folder overview

```
/office
  /admin           Admin panel pages (login, logout, dashboard, staff
                    management, attendance monitor — and, in later
                    prompts, leave/payout/reports)
    /staff          Staff CRUD + work-timing override tool
    /attendance      Today/date monitor, per-staff history, manual override
  /staff            Staff-facing pages: login, logout, dashboard, attendance
                    (their own session, separate from /admin)
  /assets/css      Shared stylesheet
  /assets/js       Shared JS (small UI behaviors)
  /includes        db.php (PDO connection), auth.php (admin session
                   helpers), staff_auth.php (staff session helpers),
                   functions.php (escaping + settings + work-timing +
                   attendance helpers)
  /sql             Numbered schema files (001_admins.sql, ...) + index.php
                   (the schema runner / DB dashboard / ad-hoc SQL tool /
                   Admin Account section — see CLAUDE.md for how it works),
                   plus .htaccess and a gitignored key.txt (admin
                   management key, created on first use)
  /cron            mark-absent.php — daily absent-marker, see step 11 above
  config.php       DB credentials + CRON_SECRET (edit this on the server)
  index.php        Redirects to /admin/login.php
  create-admin.php One-time first-admin creation script
  CLAUDE.md        Detailed technical/architecture notes for this project
  README.md        This file
```

## Schema changes going forward

**Do not edit the database by hand in phpMyAdmin.** Add a new numbered
`.sql` file to `/sql` (e.g. `008_description.sql`), then visit
`/sql/index.php` while logged in — it detects and runs new files
automatically, and lets you re-run or apply ad-hoc `ALTER` statements
safely. See `CLAUDE.md` for the full convention.

## Settings keys

Editable today only via DB Tools → Ad-hoc SQL (`UPDATE settings SET
setting_value = ... WHERE setting_key = ...`) — no settings admin page
exists yet:

| Key | Meaning | Default |
|---|---|---|
| `company_name` | Displayed app name | `Digital Ali Pro OMS` |
| `default_work_start_time` / `default_work_end_time` | Universal work hours, used when a staff member has no timing override | `09:30` / `18:30` |
| `timezone` | Informational | `Asia/Kolkata` |
| `attendance_grace_minutes` | Minutes after `default_work_start_time` (or a staff member's override start time) before a check-in counts as `'late'` | `15` |

## Roadmap

- **Prompt 1:** Foundation — skeleton, schema runner, admin login. ✅
- **Prompt 2:** Staff management, staff login, work-time overrides with
  history. ✅
- **Prompt 3 (this build):** Daily attendance check-in/check-out with
  office-WiFi verification, admin attendance monitor, absent-marking
  cron. ✅
- **Prompt 4:** Leave & WFH requests.
- **Prompt 5:** Payout & reports.
