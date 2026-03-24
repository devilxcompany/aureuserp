-- ============================================================
-- AureusERP - MySQL Initialization Script
-- This runs only on first container startup
-- ============================================================

-- Ensure UTF8MB4 character set
ALTER DATABASE aureuserp CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Create additional databases if needed
-- CREATE DATABASE IF NOT EXISTS aureuserp_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Grant privileges
GRANT ALL PRIVILEGES ON aureuserp.* TO 'aureus'@'%';
-- GRANT ALL PRIVILEGES ON aureuserp_test.* TO 'aureus'@'%';
FLUSH PRIVILEGES;
