-- Media-aanvragen (mediakit /media)
-- Herhaalbaar uitvoerbaar.

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS media_requests (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    public_request_number VARCHAR(32) NOT NULL,
    name VARCHAR(160) NOT NULL,
    organization VARCHAR(200) NOT NULL DEFAULT '',
    email VARCHAR(255) NOT NULL,
    channel_url VARCHAR(500) NULL,
    request_type VARCHAR(40) NOT NULL,
    message TEXT NOT NULL,
    preferred_date VARCHAR(40) NULL,
    audience_reach VARCHAR(1000) NULL,
    status ENUM('nieuw','in_behandeling','afgerond','afgewezen') NOT NULL DEFAULT 'nieuw',
    internal_note TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    handled_at DATETIME NULL,
    UNIQUE KEY uq_media_public_nr (public_request_number),
    INDEX idx_media_status (status),
    INDEX idx_media_type (request_type),
    INDEX idx_media_email (email),
    INDEX idx_media_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
