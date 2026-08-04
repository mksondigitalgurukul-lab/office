-- Restricts the staff-side "Lunch Start" button to a configurable time
-- window (default 12:00 PM - 3:00 PM), so it doesn't show/act outside
-- normal lunch hours. Two new settings, same pattern as
-- default_work_start_time/default_work_end_time.
--
-- No CREATE TABLE, so this does NOT auto-run — click "Re-run" for it
-- once on /sql/index.php, same as 011/014/015/017/018/019/020.
INSERT INTO settings (setting_key, setting_value) VALUES
  ('lunch_window_start_time', '12:00:00'),
  ('lunch_window_end_time', '15:00:00')
ON DUPLICATE KEY UPDATE setting_key = setting_key;
