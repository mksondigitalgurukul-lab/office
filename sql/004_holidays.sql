-- Company holidays. `applies_to` = 'specific' is reserved for targeting
-- individual staff once the staff table exists (not built in this prompt).
CREATE TABLE IF NOT EXISTS holidays (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  holiday_date DATE NOT NULL,
  name VARCHAR(150) NOT NULL,
  applies_to ENUM('all','specific') NOT NULL DEFAULT 'all',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Stub for later: which specific staff a 'specific' holiday applies to.
-- Not created yet — depends on the staff table planned for a future prompt.
-- CREATE TABLE IF NOT EXISTS holiday_staff (
--   id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
--   holiday_id INT UNSIGNED NOT NULL,
--   staff_id INT UNSIGNED NOT NULL,
--   created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
--   FOREIGN KEY (holiday_id) REFERENCES holidays(id) ON DELETE CASCADE
-- ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
