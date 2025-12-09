-- doctor_availability_fix.sql
SET NAMES utf8mb4;
CREATE TABLE IF NOT EXISTS doctor_available_times (
  id INT AUTO_INCREMENT PRIMARY KEY,
  doctor_id INT NOT NULL,
  day_of_week TINYINT NULL, -- 0=Sunday .. 6=Saturday; NULL for one-off single-date entries (not used by weekly)
  date_specific DATE NULL,  -- optional one-off date (NULL for weekly)
  start_time TIME NOT NULL,
  end_time TIME NOT NULL,
  repeat_weekly TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME DEFAULT NOW(),
  created_by INT NULL,
  CONSTRAINT fk_dat_doctor FOREIGN KEY (doctor_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- unique constraint not strictly needed here; bookings handle uniqueness
CREATE INDEX idx_dat_doctor_day ON doctor_available_times (doctor_id, day_of_week, date_specific);
