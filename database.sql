-- ============================================================================
-- Database Schema & Initial Data for System Management Portal (PlasticDX)
-- Database Name: plasticdx_db
-- ============================================================================

CREATE DATABASE IF NOT EXISTS `plasticdx_db`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `plasticdx_db`;

-- 1. Table: portal_users
CREATE TABLE IF NOT EXISTS `portal_users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(50) NOT NULL UNIQUE,
    `employee_code` VARCHAR(50) NOT NULL DEFAULT '',
    `password_hash` VARCHAR(255) NOT NULL,
    `full_name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(120) NOT NULL DEFAULT '',
    `phone` VARCHAR(30) NOT NULL DEFAULT '',
    `role` ENUM('admin', 'user') NOT NULL DEFAULT 'user',
    `department` VARCHAR(100) NOT NULL DEFAULT 'Sản xuất & QC',
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Table: portal_systems
CREATE TABLE IF NOT EXISTS `portal_systems` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `system_code` VARCHAR(50) NOT NULL,
    `name` VARCHAR(150) NOT NULL,
    `description` TEXT NOT NULL,
    `icon` VARCHAR(60) NOT NULL DEFAULT 'grid',
    `icon_color` VARCHAR(30) NOT NULL DEFAULT '#0284c7',
    `url` VARCHAR(500) NOT NULL,
    `group_type` ENUM('SERVER', 'DEVELOPMENT') NOT NULL DEFAULT 'SERVER',
    `status_label` VARCHAR(60) NOT NULL DEFAULT 'Đang hoạt động',
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `access_role` ENUM('all', 'user', 'admin') NOT NULL DEFAULT 'all',
    `display_order` INT NOT NULL DEFAULT 10,
    `version` VARCHAR(30) DEFAULT '1.0',
    `department` VARCHAR(100) DEFAULT 'Nhà máy Nhựa DX',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_group_order` (`group_type`, `display_order`),
    INDEX `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Table: portal_documents
CREATE TABLE IF NOT EXISTS `portal_documents` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `system_id` INT DEFAULT NULL,
    `title` VARCHAR(200) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `doc_type` ENUM('PDF', 'WORD', 'VIDEO', 'INTERNAL', 'WEBSITE') NOT NULL DEFAULT 'PDF',
    `file_url` VARCHAR(500) NOT NULL,
    `access_role` ENUM('all', 'user', 'admin') NOT NULL DEFAULT 'all',
    `display_order` INT NOT NULL DEFAULT 10,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_doc_type` (`doc_type`),
    INDEX `idx_doc_order` (`display_order`),
    CONSTRAINT `fk_doc_system` FOREIGN KEY (`system_id`) REFERENCES `portal_systems`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
