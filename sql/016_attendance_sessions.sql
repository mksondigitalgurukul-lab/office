-- Individual check-in/check-out segments within a single day. The
-- `attendance` table stays exactly what it's always been — one summary
-- row per staff member per day (first check-in, latest check-out,
-- computed status) — everything that already reads it (payout,
-- cron/mark-absent.php, the admin monitor/reports) keeps working
-- unchanged. This table is purely additive: it's what lets a staff
-- member check out, change their mind, and check back in the same day
-- (recording a reason for the re-check-in), and it's what
-- staff/work-report.php reads to show every segment of a day rather
-- than just the first-in/last-out summary.
CREATE TABLE IF NOT EXISTS attendance_sessions (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  attendance_id INT UNSIGNED NOT NULL,
  staff_id INT UNSIGNED NOT NULL,
  attendance_date DATE NOT NULL,
  check_in_time TIME NULL,
  check_out_time TIME NULL,
  check_in_ip VARCHAR(45) NULL,
  check_out_ip VARCHAR(45) NULL,
  recheckin_reason VARCHAR(255) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_attendance_sessions_attendance (attendance_id),
  KEY idx_attendance_sessions_staff_date (staff_id, attendance_date),
  FOREIGN KEY (attendance_id) REFERENCES attendance(id) ON DELETE CASCADE,
  FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
