-- Append-only history of each staff member's monthly salary — same
-- pattern as staff_work_time_history: a salary change always INSERTs a
-- new row, existing rows are never updated. The "current" salary for a
-- staff member on a given date = the row with the latest
-- effective_from <= that date.
CREATE TABLE IF NOT EXISTS staff_salary (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  staff_id INT UNSIGNED NOT NULL,
  monthly_salary DECIMAL(12,2) NOT NULL,
  effective_from DATE NOT NULL,
  set_by INT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_staff_salary_staff_effective (staff_id, effective_from),
  FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE CASCADE,
  FOREIGN KEY (set_by) REFERENCES admins(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
