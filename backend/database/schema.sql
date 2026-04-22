CREATE DATABASE IF NOT EXISTS usermanagement
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE usermanagement;

CREATE TABLE IF NOT EXISTS roles (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(50) NOT NULL UNIQUE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  full_name VARCHAR(255) NOT NULL,
  email VARCHAR(255) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  role_id INT UNSIGNED NOT NULL,
  phone VARCHAR(20) NULL,
  dob DATE NULL,
  gender VARCHAR(20) NULL,
  address TEXT NULL,
  avatar_url VARCHAR(500) NULL,
  status ENUM('PENDING', 'ACTIVE', 'REJECTED') NOT NULL DEFAULT 'PENDING',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_users_role
    FOREIGN KEY (role_id) REFERENCES roles(id)
    ON UPDATE CASCADE
    ON DELETE RESTRICT
);

CREATE TABLE IF NOT EXISTS login_approvals (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  admin_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  decision ENUM('APPROVE', 'REJECT') NOT NULL,
  reason TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_login_approvals_admin
    FOREIGN KEY (admin_id) REFERENCES users(id)
    ON UPDATE CASCADE
    ON DELETE RESTRICT,
  CONSTRAINT fk_login_approvals_user
    FOREIGN KEY (user_id) REFERENCES users(id)
    ON UPDATE CASCADE
    ON DELETE RESTRICT
);

CREATE TABLE IF NOT EXISTS tutors (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL UNIQUE,
  nic_number VARCHAR(50) NOT NULL,
  subject VARCHAR(100) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_tutors_user
    FOREIGN KEY (user_id) REFERENCES users(id)
    ON UPDATE CASCADE
    ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS students (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL UNIQUE,
  school_name VARCHAR(255) NOT NULL,
  grade VARCHAR(50) NOT NULL,
  siblings_count INT UNSIGNED NOT NULL DEFAULT 0,
  guardian_name VARCHAR(255) NOT NULL,
  guardian_job VARCHAR(255) NOT NULL,
  guardian_nic VARCHAR(50) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_students_user
    FOREIGN KEY (user_id) REFERENCES users(id)
    ON UPDATE CASCADE
    ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS suggestions_complaints (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  created_by INT UNSIGNED NOT NULL,
  type ENUM('SUGGESTION', 'COMPLAINT') NOT NULL,
  title VARCHAR(150) NOT NULL,
  description TEXT NOT NULL,
  status ENUM('OPEN', 'IN_PROGRESS', 'RESOLVED') NOT NULL DEFAULT 'OPEN',
  admin_note TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_suggestions_created_by
    FOREIGN KEY (created_by) REFERENCES users(id)
    ON UPDATE CASCADE
    ON DELETE CASCADE
);

CREATE INDEX idx_users_role_id ON users(role_id);
CREATE INDEX idx_users_status ON users(status);
CREATE INDEX idx_login_approvals_admin_id ON login_approvals(admin_id);
CREATE INDEX idx_login_approvals_user_id ON login_approvals(user_id);
CREATE INDEX idx_suggestions_created_by ON suggestions_complaints(created_by);
CREATE INDEX idx_suggestions_status ON suggestions_complaints(status);
CREATE INDEX idx_suggestions_type ON suggestions_complaints(type);

INSERT INTO roles (name)
VALUES
  ('ADMIN'),
  ('TUTOR'),
  ('STUDENT'),
  ('PARENT')
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- Optional: after importing this schema, create an initial admin user manually.
-- Example workflow:
-- 1. Generate a bcrypt hash in PHP:
--    password_hash('Admin123!', PASSWORD_BCRYPT)
-- 2. Insert that hash into the users table with role_id = (SELECT id FROM roles WHERE name = 'ADMIN')
-- 3. Set status = 'ACTIVE'
