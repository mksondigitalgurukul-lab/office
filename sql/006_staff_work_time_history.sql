-- Append-only history of each staff member's work timing.
--
-- Every timing change is a NEW row here — existing rows are never updated
-- or deleted, so attendance for a past date can always be checked against
-- whatever timing applied on that date.
--
-- The "current" timing for a staff member on a given date = the row with
-- the latest effective_from <= that date. work_start_time/work_end_time
-- NULL on that row means "use the universal settings.default_work_*_time"
-- from that date forward (an explicit revert-to-default, also recorded as
-- a row so the history stays complete). If a staff member has no rows at
-- all yet, they also use the universal default.
CREATE TABLE IF NOT EXISTS staff_work_time_history (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  staff_id INT UNSIGNED NOT NULL,
  work_start_time TIME NULL,
  work_end_time TIME NULL,
  effective_from DATE NOT NULL,
  set_by INT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_swth_staff_effective (staff_id, effective_from),
  FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE CASCADE,
  FOREIGN KEY (set_by) REFERENCES admins(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
