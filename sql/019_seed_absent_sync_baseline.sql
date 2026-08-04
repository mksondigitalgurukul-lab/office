-- Seeds the starting point for the page-visit-triggered absent-sync
-- (includes/functions.php's syncAbsences(), called from every admin page
-- via includes/admin-header.php). This replaces cron/mark-absent.php
-- entirely — no cron job needed.
--
-- Setting to 2026-07-31 means the first sync backfills everything from
-- 2026-08-01 onward. Every sync after that just continues from wherever
-- it left off (see syncAbsences()'s 90-day safety cap for long gaps).
--
-- No CREATE TABLE, so it does NOT auto-run — click "Re-run" for it once
-- on /sql/index.php, same as 011/014/015/017/018. ON DUPLICATE KEY UPDATE
-- is a no-op here (never overwrites a value already in progress), so
-- re-running this file is always safe.
INSERT INTO settings (setting_key, setting_value) VALUES
  ('last_absent_sync_date', '2026-07-31')
ON DUPLICATE KEY UPDATE setting_key = setting_key;
