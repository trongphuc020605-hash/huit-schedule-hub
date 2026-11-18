-- ============================================================================
-- HUIT Schedule Hub - Multi-Campus Intelligent Teaching Schedule System
-- Database Schema (MySQL 8.x, InnoDB, utf8mb4_unicode_ci, 3NF)
-- ============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE DATABASE IF NOT EXISTS `huit_db`
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE `huit_db`;

DROP TABLE IF EXISTS `branch_travel_times`;
DROP TABLE IF EXISTS `teacher_availabilities`;
DROP TABLE IF EXISTS `schedules`;
DROP TABLE IF EXISTS `teachers`;
DROP TABLE IF EXISTS `branches`;

-- ----------------------------------------------------------------------------
-- 1. Table: branches (Cơ sở đào tạo HUIT)
-- Required columns: id, branch_name, address
-- ----------------------------------------------------------------------------
CREATE TABLE `branches` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `branch_name` VARCHAR(150) NOT NULL,
    `address` VARCHAR(255) NOT NULL,
    `code` VARCHAR(20) NOT NULL,
    `campus_zone` VARCHAR(60) NOT NULL,
    `color_hex` CHAR(7) NOT NULL DEFAULT '#1D4ED8',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_branches_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 2. Table: teachers (Giảng viên & Cán bộ đào tạo)
-- Required columns: id, full_name, email, phone, expertise
-- ----------------------------------------------------------------------------
CREATE TABLE `teachers` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `full_name` VARCHAR(120) NOT NULL,
    `email` VARCHAR(150) NOT NULL,
    `phone` VARCHAR(25) NOT NULL,
    `expertise` VARCHAR(255) NOT NULL,
    `password_hash` VARCHAR(255) NOT NULL,
    `role` ENUM('admin', 'teacher') NOT NULL DEFAULT 'teacher',
    `portal_token` CHAR(64) NOT NULL,
    `max_periods_per_week` TINYINT UNSIGNED NOT NULL DEFAULT 18,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_teachers_email` (`email`),
    UNIQUE KEY `uq_teachers_portal_token` (`portal_token`),
    KEY `idx_teachers_role` (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 3. Table: schedules (Lịch phân công giảng dạy)
-- Required columns: id, teacher_id, branch_id, subject_name, room, day_of_week, shift_or_periods
-- ----------------------------------------------------------------------------
CREATE TABLE `schedules` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `teacher_id` INT UNSIGNED NOT NULL,
    `branch_id` INT UNSIGNED NOT NULL,
    `subject_name` VARCHAR(180) NOT NULL,
    `room` VARCHAR(50) NOT NULL,
    `day_of_week` TINYINT UNSIGNED NOT NULL,
    `shift_or_periods` VARCHAR(80) NOT NULL,
    `start_period` TINYINT UNSIGNED NOT NULL,
    `end_period` TINYINT UNSIGNED NOT NULL,
    `start_date` DATE NOT NULL,
    `end_date` DATE NOT NULL,
    `notes` VARCHAR(255) NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    CONSTRAINT `fk_schedules_teacher`
        FOREIGN KEY (`teacher_id`) REFERENCES `teachers` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_schedules_branch`
        FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `chk_schedules_day` CHECK (`day_of_week` BETWEEN 2 AND 8),
    CONSTRAINT `chk_schedules_periods` CHECK (
        `start_period` BETWEEN 1 AND 15
        AND `end_period` BETWEEN 1 AND 15
        AND `end_period` >= `start_period`
    ),
    CONSTRAINT `chk_schedules_dates` CHECK (`end_date` >= `start_date`),
    KEY `idx_sched_teacher_slot` (`teacher_id`, `day_of_week`, `start_period`, `end_period`, `start_date`, `end_date`),
    KEY `idx_sched_branch_room_slot` (`branch_id`, `room`, `day_of_week`, `start_period`, `end_period`, `start_date`, `end_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 4. Table: teacher_availabilities (Tiết trống / Khung giờ đăng ký giảng dạy)
-- ----------------------------------------------------------------------------
CREATE TABLE `teacher_availabilities` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `teacher_id` INT UNSIGNED NOT NULL,
    `day_of_week` TINYINT UNSIGNED NOT NULL,
    `shift_or_periods` VARCHAR(80) NOT NULL,
    `start_period` TINYINT UNSIGNED NOT NULL,
    `end_period` TINYINT UNSIGNED NOT NULL,
    `preferred_branch_id` INT UNSIGNED NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_avail` (`teacher_id`, `day_of_week`, `start_period`, `end_period`),
    CONSTRAINT `fk_avail_teacher`
        FOREIGN KEY (`teacher_id`) REFERENCES `teachers` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_avail_branch`
        FOREIGN KEY (`preferred_branch_id`) REFERENCES `branches` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `chk_avail_day` CHECK (`day_of_week` BETWEEN 2 AND 8),
    CONSTRAINT `chk_avail_periods` CHECK (
        `start_period` BETWEEN 1 AND 15
        AND `end_period` BETWEEN 1 AND 15
        AND `end_period` >= `start_period`
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 5. Table: branch_travel_times (Ma trận thời gian di chuyển thực tế giữa các cơ sở)
-- ----------------------------------------------------------------------------
CREATE TABLE `branch_travel_times` (
    `from_branch_id` INT UNSIGNED NOT NULL,
    `to_branch_id` INT UNSIGNED NOT NULL,
    `travel_minutes` SMALLINT UNSIGNED NOT NULL,
    `distance_km` DECIMAL(4,1) NOT NULL,
    PRIMARY KEY (`from_branch_id`, `to_branch_id`),
    CONSTRAINT `fk_travel_from`
        FOREIGN KEY (`from_branch_id`) REFERENCES `branches` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_travel_to`
        FOREIGN KEY (`to_branch_id`) REFERENCES `branches` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
