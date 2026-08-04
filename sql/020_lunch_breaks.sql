-- Lunch break tracking, built on top of the existing attendance_sessions
-- multi-check-in mechanism (a lunch break is just a session close/reopen
-- pair, tagged so the app can tell it apart from a regular re-check-in).
--
-- break_type marks a session as having been CLOSED for a lunch break
-- (set by "Lunch Start" on staff/attendance.php); NULL for every other
-- session close. lunch_warning_minutes is the threshold (in minutes)
-- above which a lunch break shows a warning — editable via the admin
-- Settings page like any other setting.
--
-- No CREATE TABLE, so this does NOT auto-run — click "Re-run" for it
-- once on /sql/index.php, same as 011/014/015/017/018/019.
ALTER TABLE attendance_sessions
  ADD COLUMN IF NOT EXISTS break_type ENUM('lunch') NULL AFTER recheckin_reason;

INSERT INTO settings (setting_key, setting_value) VALUES
  ('lunch_warning_minutes', '60')
ON DUPLICATE KEY UPDATE setting_key = setting_key;
