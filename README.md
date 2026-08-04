# Digital Ali Pro OMS

Single-tenant Office Management System (staff attendance, leave, and
payout) for one company, built as plain PHP + MySQL for cPanel shared
hosting. Deployed at
[www.digitalalipro.in/office](https://www.digitalalipro.in/office).

> This is Prompt 1 of a multi-prompt build: project skeleton, database
> schema runner, and admin login only. Staff management, attendance, leave,
> and payout are not built yet — see `CLAUDE.md` for the full roadmap.

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
   Do not commit your real credentials back into git.
4. **Create the schema.** Visit `https://www.digitalalipro.in/office/sql/index.php`
   in a browser. On a brand-new install (no admin account yet) this page is
   open in **setup mode** — it will automatically create the `admins`,
   `settings`, `office_locations`, and `holidays` tables and show you their
   structure.
5. **Create the first admin.** Visit
   `https://www.digitalalipro.in/office/create-admin.php` and fill in a
   name, email, and password (8+ characters). This can only be run once —
   it refuses to run again as soon as one admin account exists.

   Alternatively, over SSH:
   ```bash
   php create-admin.php "Your Name" you@example.com "a-strong-password"
   ```
6. **Secure `create-admin.php`.** After creating the first admin, delete
   `create-admin.php` from the server (or block access to it in
   `.htaccess`) — it's a one-time setup script and shouldn't stay reachable.
7. **Log in.** Go to `https://www.digitalalipro.in/office/admin/login.php`
   and sign in with the admin account you just created. You'll land on the
   dashboard. From there, **DB Tools** in the nav (`/sql/index.php`) is now
   locked behind login, as normal — use it going forward for any future
   schema changes shipped in later prompts.

## Local development

1. Point `config.php` at a local MySQL/MariaDB database (same steps as
   above, using `127.0.0.1` or `localhost`).
2. Run PHP's built-in server from the project root:
   ```bash
   php -S localhost:8000
   ```
3. Visit `http://localhost:8000/sql/index.php` to create the schema, then
   `http://localhost:8000/create-admin.php` to create your first admin.

## Folder overview

```
/office
  /admin          Admin panel pages (login, logout, dashboard, and — in
                   later prompts — staff/attendance/leave/payout/reports)
  /assets/css      Shared stylesheet
  /assets/js       Shared JS (small UI behaviors)
  /includes        db.php (PDO connection), auth.php (session helpers),
                   functions.php (escaping + settings helpers)
  /sql             Numbered schema files (001_admins.sql, ...) + index.php
                   (the schema runner / DB dashboard / ad-hoc SQL tool —
                   see CLAUDE.md for how it works)
  config.php       DB credentials (edit this on the server)
  index.php        Redirects to /admin/login.php
  create-admin.php One-time first-admin creation script
  CLAUDE.md        Detailed technical/architecture notes for this project
  README.md        This file
```

## Schema changes going forward

**Do not edit the database by hand in phpMyAdmin.** Add a new numbered
`.sql` file to `/sql` (e.g. `005_staff.sql`), then visit `/sql/index.php`
while logged in — it detects and runs new files automatically, and lets you
re-run or apply ad-hoc `ALTER` statements safely. See `CLAUDE.md` for the
full convention.

## Roadmap

- **Prompt 1 (this build):** Foundation — skeleton, schema runner, admin
  login. ✅
- **Prompt 2:** Staff management.
- **Prompt 3:** Attendance / WiFi-based check-in.
- **Prompt 4:** Leave & WFH requests.
- **Prompt 5:** Payout & reports.
