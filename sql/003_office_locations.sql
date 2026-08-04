-- Office branches / IPs, used later for WiFi-based attendance check-in.
CREATE TABLE IF NOT EXISTS office_locations (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  location_name VARCHAR(150) NOT NULL,
  ip_address VARCHAR(45) NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
