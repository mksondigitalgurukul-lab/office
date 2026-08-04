-- Add 'on_leave' to attendance.status so cron/mark-absent.php can record
-- "was on approved leave" distinctly from a genuine unexplained absence,
-- instead of silently skipping the day. See CLAUDE.md "Attendance" for
-- the full leave/WFH-vs-absent decision this supports.
--
-- This file has no CREATE TABLE, so sql/index.php's schema runner will
-- NOT auto-run it (it only auto-runs files whose primary table doesn't
-- exist yet). Click "Re-run" for this file once on /sql/index.php after
-- deploying — safe to click more than once, MODIFY COLUMN to the same
-- definition is a no-op on repeat runs.
ALTER TABLE attendance
  MODIFY COLUMN status ENUM('present','late','half_day','absent','on_leave') NOT NULL DEFAULT 'absent';
