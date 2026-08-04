-- Employee records + their own login credentials.
CREATE TABLE IF NOT EXISTS staff (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  full_name VARCHAR(150) NOT NULL,
  email VARCHAR(150) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  phone VARCHAR(20) NULL,
  designation VARCHAR(100) NULL,
  department VARCHAR(100) NULL,
  work_mode ENUM('office','wfh','hybrid') NOT NULL DEFAULT 'office',
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  joined_date DATE NULL,
  created_by INT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_staff_email (email),
  KEY idx_staff_status (status),
  KEY idx_staff_department (department),
  FOREIGN KEY (created_by) REFERENCES admins(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
