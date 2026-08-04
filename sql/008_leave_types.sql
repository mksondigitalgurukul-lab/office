-- Kinds of leave staff can request.
CREATE TABLE IF NOT EXISTS leave_types (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  is_paid TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_leave_types_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO leave_types (name, is_paid) VALUES
  ('Sick', 1),
  ('Casual', 1),
  ('Paid', 1),
  ('Unpaid', 0)
ON DUPLICATE KEY UPDATE name = name;
