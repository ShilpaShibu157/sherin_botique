CREATE DATABASE IF NOT EXISTS sherins_boutique
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE sherins_boutique;

CREATE TABLE IF NOT EXISTS customer_leads (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    phone VARCHAR(32) NOT NULL,
    interest VARCHAR(120) NOT NULL,
    message VARCHAR(1000) NOT NULL DEFAULT '',
    created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
