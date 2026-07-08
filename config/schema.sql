-- ============================================================
--  Academic Performance Analysis Dashboard — Database Schema
-- ============================================================

CREATE DATABASE IF NOT EXISTS ajim_dashboard
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE ajim_dashboard;

-- ----------------------------------------------------------
-- 1. Users
-- ----------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(100)        NOT NULL,
    email       VARCHAR(150)        NOT NULL UNIQUE,
    password    VARCHAR(255)        NOT NULL,
    role        ENUM('admin','student') NOT NULL DEFAULT 'student',
    student_id  INT UNSIGNED        DEFAULT NULL,  -- links student-role users to their student record
    created_at  TIMESTAMP           NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP           NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_users_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ----------------------------------------------------------
-- 2. Students
-- ----------------------------------------------------------
CREATE TABLE IF NOT EXISTS students (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_code VARCHAR(20)        NOT NULL UNIQUE,
    name        VARCHAR(100)        NOT NULL,
    email       VARCHAR(150)                 UNIQUE,
    department  VARCHAR(100)        NOT NULL,
    semester    TINYINT UNSIGNED    NOT NULL DEFAULT 1,
    enrolled_by INT UNSIGNED        NOT NULL,
    created_at  TIMESTAMP           NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP           NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_students_user FOREIGN KEY (enrolled_by) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ----------------------------------------------------------
-- 3. Performance Records
-- ----------------------------------------------------------
CREATE TABLE IF NOT EXISTS records (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id  INT UNSIGNED        NOT NULL,
    subject     VARCHAR(100)        NOT NULL,
    marks       DECIMAL(5,2)        NOT NULL,
    max_marks   DECIMAL(5,2)        NOT NULL DEFAULT 100,
    exam_type   ENUM('midterm','final','assignment','quiz') NOT NULL DEFAULT 'final',
    exam_date   DATE                NOT NULL,
    remarks     VARCHAR(255)                 DEFAULT NULL,
    added_by    INT UNSIGNED        NOT NULL,
    created_at  TIMESTAMP           NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP           NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_records_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    CONSTRAINT fk_records_user    FOREIGN KEY (added_by)   REFERENCES users(id)    ON DELETE CASCADE,
    CONSTRAINT chk_marks CHECK (marks >= 0 AND marks <= max_marks)
) ENGINE=InnoDB;

-- ----------------------------------------------------------
-- 4. Reports (aggregated performance snapshots)
-- ----------------------------------------------------------
CREATE TABLE IF NOT EXISTS reports (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id      INT UNSIGNED        NOT NULL,
    report_date     DATE                NOT NULL,
    total_subjects  TINYINT UNSIGNED    NOT NULL DEFAULT 0,
    avg_percentage  DECIMAL(5,2)        NOT NULL DEFAULT 0.00,
    highest_marks   DECIMAL(5,2)                 DEFAULT NULL,
    lowest_marks    DECIMAL(5,2)                 DEFAULT NULL,
    grade           CHAR(2)                      DEFAULT NULL,
    performance_status ENUM('Excellent','Good','Average','Below Average','Fail') NOT NULL DEFAULT 'Average',
    generated_by    INT UNSIGNED        NOT NULL,
    created_at      TIMESTAMP           NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_reports_student FOREIGN KEY (student_id)   REFERENCES students(id) ON DELETE CASCADE,
    CONSTRAINT fk_reports_user    FOREIGN KEY (generated_by) REFERENCES users(id)    ON DELETE CASCADE
) ENGINE=InnoDB;

-- ----------------------------------------------------------
-- Indexes for faster lookups
-- ----------------------------------------------------------
CREATE INDEX idx_records_student  ON records(student_id);
CREATE INDEX idx_records_subject  ON records(subject);
CREATE INDEX idx_records_examdate ON records(exam_date);
CREATE INDEX idx_reports_student  ON reports(student_id);
CREATE INDEX idx_reports_date     ON reports(report_date);

-- ----------------------------------------------------------
-- Default admin user  (password: Admin@1234)
-- ----------------------------------------------------------
INSERT INTO users (name, email, password, role, student_id) VALUES
('Administrator', 'admin@ajim.com', '$2y$12$Qw3eRtY7uIoPaSdFgHjKlOZxCvBnMq1W2E3R4T5Y6U7I8O9P0A1B2', 'admin', NULL);

-- NOTE: The hash above is a placeholder.
-- Run this PHP snippet once to regenerate:
--   echo password_hash('Admin@1234', PASSWORD_BCRYPT, ['cost'=>12]);
-- Then UPDATE users SET password='<new_hash>' WHERE email='admin@ajim.com';
