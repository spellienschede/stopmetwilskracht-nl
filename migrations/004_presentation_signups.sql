-- Aanmeldingen online boekpresentatie
-- Herhaalbaar uitvoerbaar.

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS presentation_signups (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    public_signup_number VARCHAR(32) NOT NULL,
    name VARCHAR(160) NOT NULL,
    email VARCHAR(255) NOT NULL,
    notes TEXT NULL,
    event_date DATE NOT NULL,
    event_time VARCHAR(10) NOT NULL DEFAULT '19:30',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_presentation_public_nr (public_signup_number),
    UNIQUE KEY uq_presentation_email_event (email, event_date),
    INDEX idx_presentation_created (created_at),
    INDEX idx_presentation_event (event_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
