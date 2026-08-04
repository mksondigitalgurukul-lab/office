-- Daily check-in/check-out records. One row per staff member per day
-- (enforced by the unique key below); status is always computed by the
-- app, never entered directly by staff.
CREATE TABLE IF NOT EXISTS attendance (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  staff_id INT UNSIGNED NOT NULL,
  attendance_date DATE NOT NULL,
  check_in_time TIME NULL,
  check_out_time TIME NULL,
  check_in_ip VARCHAR(45) NULL,
  check_out_ip VARCHAR(45) NULL,
  work_location ENUM('office_verified','office_manual','wfh','unverified') NOT NULL DEFAULT 'unverified',
  status ENUM('present','late','half_day','absent') NOT NULL DEFAULT 'absent',
  notes TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_attendance_staff_date (staff_id, attendance_date),
  KEY idx_attendance_date (attendance_date),
  FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Grace period (minutes) added to a staff member's scheduled start time
-- before a check-in counts as 'late'. Seeded here (not in 002_settings.sql,
-- which only ever runs once) so it arrives idempotently alongside the
-- feature that reads it.
INSERT INTO settings (setting_key, setting_value) VALUES
  ('attendance_grace_minutes', '15')
ON DUPLICATE KEY UPDATE setting_key = setting_key;
