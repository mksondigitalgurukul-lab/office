-- WFH requests for a single day. Either staff-submitted (created_by_type
-- = 'staff', created_by_id = the staff member's own id, status starts
-- 'pending') or admin-assigned (created_by_type = 'admin', created_by_id
-- = the admin's id, status is 'approved' immediately — no self-review).
-- created_by_id intentionally has no foreign key: it points at staff.id
-- or admins.id depending on created_by_type (a polymorphic reference).
--
-- Unique on (staff_id, wfh_date): at most one request per staff per day.
-- Both the staff submission form and the admin "assign WFH" form use
-- INSERT ... ON DUPLICATE KEY UPDATE against this key — a staff member
-- resubmitting after rejection, or an admin re-assigning, updates the
-- same row rather than creating a duplicate.
CREATE TABLE IF NOT EXISTS wfh_requests (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  staff_id INT UNSIGNED NOT NULL,
  wfh_date DATE NOT NULL,
  reason TEXT NULL,
  status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  created_by_type ENUM('staff','admin') NOT NULL,
  created_by_id INT UNSIGNED NOT NULL,
  reviewed_by INT UNSIGNED NULL,
  reviewed_at TIMESTAMP NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_wfh_staff_date (staff_id, wfh_date),
  KEY idx_wfh_requests_status (status),
  FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE CASCADE,
  FOREIGN KEY (reviewed_by) REFERENCES admins(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
