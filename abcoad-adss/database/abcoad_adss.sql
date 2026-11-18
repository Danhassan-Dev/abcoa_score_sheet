-- ABCOAD Digital Examination Score Sheet Archive System
-- Database: abcoad_adss
-- MySQL / MariaDB, InnoDB, utf8mb4

CREATE DATABASE IF NOT EXISTS abcoad_adss
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE abcoad_adss;

CREATE TABLE departments (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  department_name VARCHAR(150) NOT NULL,
  department_code VARCHAR(30) NOT NULL UNIQUE,
  description TEXT NULL,
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  department_id INT UNSIGNED NULL,
  full_name VARCHAR(150) NOT NULL,
  username VARCHAR(60) NOT NULL UNIQUE,
  email VARCHAR(150) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('admin','staff') NOT NULL DEFAULT 'staff',
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  last_login_at DATETIME NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_users_department FOREIGN KEY (department_id)
    REFERENCES departments(id) ON DELETE SET NULL ON UPDATE CASCADE,
  INDEX idx_users_role (role),
  INDEX idx_users_status (status),
  INDEX idx_users_department (department_id)
) ENGINE=InnoDB;

CREATE TABLE academic_sessions (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  session_name VARCHAR(30) NOT NULL UNIQUE,
  start_date DATE NULL,
  end_date DATE NULL,
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE semesters (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  semester_name VARCHAR(50) NOT NULL UNIQUE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE courses (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  department_id INT UNSIGNED NOT NULL,
  course_code VARCHAR(30) NOT NULL,
  course_title VARCHAR(200) NOT NULL,
  description TEXT NULL,
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_courses_department FOREIGN KEY (department_id)
    REFERENCES departments(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  UNIQUE KEY uq_course_department (department_id, course_code),
  INDEX idx_courses_title (course_title)
) ENGINE=InnoDB;

CREATE TABLE examination_types (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  examination_name VARCHAR(100) NOT NULL UNIQUE,
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE score_sheets (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  course_id INT UNSIGNED NOT NULL,
  academic_session_id INT UNSIGNED NOT NULL,
  semester_id INT UNSIGNED NOT NULL,
  examination_type_id INT UNSIGNED NULL,
  uploaded_by INT UNSIGNED NOT NULL,
  lecturer_name VARCHAR(150) NOT NULL,
  reference_number VARCHAR(100) NULL,
  description TEXT NULL,
  original_filename VARCHAR(255) NOT NULL,
  stored_filename VARCHAR(255) NOT NULL UNIQUE,
  file_path VARCHAR(500) NOT NULL,
  mime_type VARCHAR(100) NOT NULL,
  file_size BIGINT UNSIGNED NOT NULL,
  file_hash CHAR(64) NOT NULL,
  verification_status ENUM('pending','verified','rejected') NOT NULL DEFAULT 'pending',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_score_course FOREIGN KEY (course_id)
    REFERENCES courses(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_score_session FOREIGN KEY (academic_session_id)
    REFERENCES academic_sessions(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_score_semester FOREIGN KEY (semester_id)
    REFERENCES semesters(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_score_exam_type FOREIGN KEY (examination_type_id)
    REFERENCES examination_types(id) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_score_uploader FOREIGN KEY (uploaded_by)
    REFERENCES users(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  INDEX idx_score_course (course_id),
  INDEX idx_score_session (academic_session_id),
  INDEX idx_score_semester (semester_id),
  INDEX idx_score_status (verification_status),
  INDEX idx_score_uploader (uploaded_by),
  INDEX idx_score_created (created_at)
) ENGINE=InnoDB;

CREATE TABLE document_verifications (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  score_sheet_id BIGINT UNSIGNED NOT NULL,
  reviewed_by INT UNSIGNED NOT NULL,
  decision ENUM('verified','rejected') NOT NULL,
  rejection_reason TEXT NULL,
  comments TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_verification_document FOREIGN KEY (score_sheet_id)
    REFERENCES score_sheets(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_verification_admin FOREIGN KEY (reviewed_by)
    REFERENCES users(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  INDEX idx_verification_document (score_sheet_id),
  INDEX idx_verification_admin (reviewed_by)
) ENGINE=InnoDB;

CREATE TABLE activity_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NULL,
  action VARCHAR(100) NOT NULL,
  entity_type VARCHAR(100) NULL,
  entity_id BIGINT UNSIGNED NULL,
  details TEXT NULL,
  ip_address VARCHAR(45) NULL,
  user_agent VARCHAR(500) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_activity_user FOREIGN KEY (user_id)
    REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE,
  INDEX idx_activity_user (user_id),
  INDEX idx_activity_action (action),
  INDEX idx_activity_created (created_at)
) ENGINE=InnoDB;

CREATE TABLE system_settings (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  setting_key VARCHAR(100) NOT NULL UNIQUE,
  setting_value TEXT NULL,
  description VARCHAR(255) NULL,
  updated_by INT UNSIGNED NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_settings_user FOREIGN KEY (updated_by)
    REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB;
