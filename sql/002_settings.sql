-- Universal key/value config used across the app.
CREATE TABLE IF NOT EXISTS settings (
  setting_key VARCHAR(100) NOT NULL PRIMARY KEY,
  setting_value TEXT NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO settings (setting_key, setting_value) VALUES
  ('company_name', 'Digital Ali Pro OMS'),
  ('default_work_start_time', '09:30'),
  ('default_work_end_time', '18:30'),
  ('timezone', 'Asia/Kolkata')
ON DUPLICATE KEY UPDATE setting_key = setting_key;
