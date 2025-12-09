CREATE DATABASE IF NOT EXISTS doctor_appointment CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE doctor_appointment;

-- users table
CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  role ENUM('patient','doctor','admin') NOT NULL,
  username VARCHAR(64) NOT NULL UNIQUE,
  full_name VARCHAR(128),
  email VARCHAR(128) NOT NULL UNIQUE,
  contact VARCHAR(32),
  password_hash VARCHAR(255) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  status ENUM('active','disabled','pending') NOT NULL DEFAULT 'pending',
  created_by INT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- patients_profiles
CREATE TABLE IF NOT EXISTS patients_profiles (
  user_id INT PRIMARY KEY,
  diabetes ENUM('type1','type2','gestational','prediabetic','non') DEFAULT 'non',
  thyroid ENUM('hypo','hyper','none') DEFAULT 'none',
  blood_pressure ENUM('hypertension','hypotension','normal') DEFAULT 'normal',
  asthma ENUM('yes','no') DEFAULT 'no',
  age INT,
  blood_group VARCHAR(5),
  allergies ENUM('yes','no') DEFAULT 'no',
  allergies_text TEXT,
  past_surgeries ENUM('yes','no') DEFAULT 'no',
  surgeries_text TEXT,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- doctors_profiles
CREATE TABLE IF NOT EXISTS doctors_profiles (
  user_id INT PRIMARY KEY,
  license_no VARCHAR(64) NOT NULL,
  experience INT DEFAULT 0,
  specialty VARCHAR(128),
  fee DECIMAL(10,2) DEFAULT 0.00,
  bio TEXT,
  image VARCHAR(255),
  status ENUM('approved','pending','disabled') DEFAULT 'pending',
  created_by INT NULL,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- doctor_available_times
CREATE TABLE IF NOT EXISTS doctor_available_times (
  id INT AUTO_INCREMENT PRIMARY KEY,
  doctor_id INT NOT NULL,
  day_of_week TINYINT NULL,
  date_for DATE NULL,
  start_time TIME NOT NULL,
  end_time TIME NOT NULL,
  repeat_weekly BOOLEAN DEFAULT TRUE,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (doctor_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- doctor_dayoffs
CREATE TABLE IF NOT EXISTS doctor_dayoffs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  doctor_id INT NOT NULL,
  date DATE NOT NULL,
  reason VARCHAR(255),
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (doctor_id) REFERENCES users(id) ON DELETE CASCADE,
  UNIQUE (doctor_id, date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- appointments
CREATE TABLE IF NOT EXISTS appointments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  patient_id INT NOT NULL,
  doctor_id INT NOT NULL,
  date DATE NOT NULL,
  start_time TIME NOT NULL,
  end_time TIME NOT NULL,
  status ENUM('booked','cancelled','completed') NOT NULL DEFAULT 'booked',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  created_by INT NULL,
  cancelled_by_role ENUM('patient','doctor','admin') NULL,
  cancel_reason VARCHAR(255) NULL,
  cancelled_at DATETIME NULL,
  FOREIGN KEY (patient_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (doctor_id) REFERENCES users(id) ON DELETE CASCADE,
  UNIQUE KEY unique_slot (doctor_id, date, start_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- notifications
CREATE TABLE IF NOT EXISTS notifications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  title VARCHAR(255) NOT NULL,
  message TEXT NOT NULL,
  is_read BOOLEAN DEFAULT FALSE,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- payments / revenue
CREATE TABLE IF NOT EXISTS payments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  appointment_id INT,
  amount DECIMAL(10,2) NOT NULL,
  method VARCHAR(64) DEFAULT 'stub',
  status ENUM('paid','pending','refunded') DEFAULT 'pending',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- logs (audit)
CREATE TABLE IF NOT EXISTS logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  actor_id INT NULL,
  actor_role ENUM('patient','doctor','admin') NULL,
  event_type VARCHAR(128),
  detail TEXT,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

