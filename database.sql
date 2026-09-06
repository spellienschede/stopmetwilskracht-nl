-- Database schema voor Grippartner nieuwsbrief aanmeldingen

CREATE DATABASE IF NOT EXISTS grippartner CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE grippartner;

CREATE TABLE IF NOT EXISTS nieuwsbrief_aanmeldingen (
    id INT AUTO_INCREMENT PRIMARY KEY,
    voornaam VARCHAR(100) NOT NULL,
    achternaam VARCHAR(100) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    aanmeldmoment DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    uitgeschreven TINYINT(1) NOT NULL DEFAULT 0,
    uitgeschreven_datum DATETIME NULL,
    INDEX idx_email (email),
    INDEX idx_uitgeschreven (uitgeschreven),
    INDEX idx_aanmeldmoment (aanmeldmoment)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;






