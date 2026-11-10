-- CV & Resume Builder — database schema
-- Import in phpMyAdmin (Import tab) or run: mysql -u root < database.sql

CREATE DATABASE IF NOT EXISTS cv_builder_db
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE cv_builder_db;

-- Accounts use a phone number as the login (no email).
CREATE TABLE IF NOT EXISTS users (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name          VARCHAR(100) NOT NULL,
  phone         VARCHAR(20)  NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  role          ENUM('admin','user')     NOT NULL DEFAULT 'user',
  status        ENUM('active','blocked') NOT NULL DEFAULT 'active',
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_users_phone (phone)
) ENGINE=InnoDB;

-- Login sessions. Only a SHA-256 hash of each token is stored.
CREATE TABLE IF NOT EXISTS auth_tokens (
  id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id    INT UNSIGNED NOT NULL,
  token_hash CHAR(64)     NOT NULL,
  expires_at DATETIME     NOT NULL,
  created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_token_hash (token_hash),
  KEY idx_tokens_user (user_id),
  CONSTRAINT fk_tokens_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- One row per CV. 'settings' = template look (font, size, spacing, colour),
-- 'data' = all CV content as JSON.
CREATE TABLE IF NOT EXISTS resumes (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id    INT UNSIGNED NOT NULL,
  title      VARCHAR(150) NOT NULL DEFAULT 'Untitled Resume',
  template   VARCHAR(30)  NOT NULL DEFAULT 'modern',
  settings   JSON NOT NULL,
  data       JSON NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_resumes_user (user_id),
  CONSTRAINT fk_resumes_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Default admin (the only seeded account; no sample users).
-- Phone: 099311622 — the password is stored as a bcrypt hash, never in plain text.
-- INSERT IGNORE: re-importing this file will not overwrite a changed password.
INSERT IGNORE INTO users (name, phone, password_hash, role, status)
VALUES ('Veasna', '099311622', '$2y$12$OtXfTLglU6N8wliWNpmYhOMzWcvzkf/RmDzr3x/g0YXCBGwht5c3.', 'admin', 'active');
