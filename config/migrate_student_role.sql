-- =============================================================
--  Migration: Student Role Redesign
--  Run this once against the ajim_dashboard database.
-- =============================================================

USE ajim_dashboard;

-- 1. Add student_id column to users (nullable — admins won't have one)
ALTER TABLE users
    ADD COLUMN student_id INT UNSIGNED DEFAULT NULL AFTER role,
    ADD CONSTRAINT fk_users_student
        FOREIGN KEY (student_id) REFERENCES students(id)
        ON DELETE SET NULL;

-- 2. Change the ENUM so 'user' becomes 'student'
--    Step A: temporarily allow both values
ALTER TABLE users
    MODIFY COLUMN role ENUM('admin','user','student') NOT NULL DEFAULT 'student';

-- Step B: rename existing 'user' rows to 'student'
UPDATE users SET role = 'student' WHERE role = 'user';

-- Step C: drop the old 'user' value
ALTER TABLE users
    MODIFY COLUMN role ENUM('admin','student') NOT NULL DEFAULT 'student';
