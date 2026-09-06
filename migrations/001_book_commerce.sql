-- Grippartner boekverkoop & promo-workflow
-- Herhaalbaar uitvoerbaar (IF NOT EXISTS / veilige ALTER-checks).
-- Charset: utf8mb4 | Engine: InnoDB
-- Tijdzone-app: Europe/Amsterdam (PHP); MySQL slaat DATETIME op als lokale serverwaarde.

SET NAMES utf8mb4;
SET time_zone = '+00:00';

-- Bestaande nieuwsbrieftabel uitbreiden met toestemmingsbewijs
CREATE TABLE IF NOT EXISTS nieuwsbrief_aanmeldingen (
    id INT AUTO_INCREMENT PRIMARY KEY,
    voornaam VARCHAR(100) NOT NULL,
    achternaam VARCHAR(100) NOT NULL,
    email VARCHAR(255) NOT NULL,
    aanmeldmoment DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    uitgeschreven TINYINT(1) NOT NULL DEFAULT 0,
    uitgeschreven_datum DATETIME NULL,
    consent_source VARCHAR(64) NULL,
    consent_at DATETIME NULL,
    UNIQUE KEY uq_nb_email (email),
    INDEX idx_email (email),
    INDEX idx_uitgeschreven (uitgeschreven),
    INDEX idx_aanmeldmoment (aanmeldmoment)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Kolommen toevoegen als ze nog ontbreken (MySQL 8 compatible via procedure-achtige checks)
SET @db := DATABASE();

SET @sql := (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE nieuwsbrief_aanmeldingen ADD COLUMN consent_source VARCHAR(64) NULL AFTER uitgeschreven_datum',
    'SELECT 1'
  )
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'nieuwsbrief_aanmeldingen' AND COLUMN_NAME = 'consent_source'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE nieuwsbrief_aanmeldingen ADD COLUMN consent_at DATETIME NULL AFTER consent_source',
    'SELECT 1'
  )
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'nieuwsbrief_aanmeldingen' AND COLUMN_NAME = 'consent_at'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Beheerders
CREATE TABLE IF NOT EXISTS admins (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(64) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_login_at DATETIME NULL,
    UNIQUE KEY uq_admin_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Bestellingen (betaald + gratis promo-fulfilment)
CREATE TABLE IF NOT EXISTS book_orders (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    public_order_number VARCHAR(32) NOT NULL,
    source ENUM('mollie','approved_promo') NOT NULL DEFAULT 'mollie',
    promo_application_id INT UNSIGNED NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    email VARCHAR(255) NOT NULL,
    street VARCHAR(150) NOT NULL,
    house_number VARCHAR(20) NOT NULL,
    house_addition VARCHAR(20) NULL,
    postal_code VARCHAR(20) NOT NULL,
    city VARCHAR(100) NOT NULL,
    country CHAR(2) NOT NULL DEFAULT 'NL',
    quantity INT UNSIGNED NOT NULL DEFAULT 1,
    unit_price_cents INT UNSIGNED NOT NULL,
    total_cents INT UNSIGNED NOT NULL,
    currency CHAR(3) NOT NULL DEFAULT 'EUR',
    payment_status VARCHAR(32) NOT NULL DEFAULT 'pending_payment',
    fulfilment_status VARCHAR(32) NOT NULL DEFAULT 'awaiting_payment',
    mollie_payment_id VARCHAR(64) NULL,
    marketing_consent TINYINT(1) NOT NULL DEFAULT 0,
    marketing_consent_at DATETIME NULL,
    internal_note TEXT NULL,
    customer_email_sent_at DATETIME NULL,
    admin_email_sent_at DATETIME NULL,
    shipped_email_sent_at DATETIME NULL,
    paid_at DATETIME NULL,
    shipped_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_order_public (public_order_number),
    UNIQUE KEY uq_order_mollie (mollie_payment_id),
    UNIQUE KEY uq_order_promo (promo_application_id),
    INDEX idx_order_email (email),
    INDEX idx_order_payment_status (payment_status),
    INDEX idx_order_fulfilment (fulfilment_status),
    INDEX idx_order_created (created_at),
    INDEX idx_order_source (source)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Promo-aanvragen (volledige workflow sectie 17)
CREATE TABLE IF NOT EXISTS promo_applications (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    public_application_number VARCHAR(32) NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    email VARCHAR(255) NOT NULL,
    street VARCHAR(150) NOT NULL,
    house_number VARCHAR(20) NOT NULL,
    house_addition VARCHAR(20) NULL,
    postal_code VARCHAR(20) NOT NULL,
    city VARCHAR(100) NOT NULL,
    country CHAR(2) NOT NULL DEFAULT 'NL',
    idea_description TEXT NOT NULL,
    audience_reach TEXT NULL,
    proposed_planning TEXT NULL,
    status VARCHAR(40) NOT NULL DEFAULT 'submitted',
    internal_note TEXT NULL,
    execution_agreement TEXT NULL,
    execution_deadline DATE NULL,
    idea_approved_at DATETIME NULL,
    idea_rejected_at DATETIME NULL,
    idea_rejection_reason TEXT NULL,
    idea_changes_note TEXT NULL,
    idea_changes_requested_at DATETIME NULL,
    execution_token_hash CHAR(64) NULL,
    execution_token_created_at DATETIME NULL,
    execution_submitted_at DATETIME NULL,
    execution_description TEXT NULL,
    execution_performed_on DATE NULL,
    execution_links TEXT NULL,
    execution_reach_notes TEXT NULL,
    execution_extra_notes TEXT NULL,
    execution_changes_note TEXT NULL,
    execution_changes_requested_at DATETIME NULL,
    execution_approved_at DATETIME NULL,
    execution_rejected_at DATETIME NULL,
    execution_rejection_reason TEXT NULL,
    reward_order_id INT UNSIGNED NULL,
    marketing_consent TINYINT(1) NOT NULL DEFAULT 0,
    marketing_consent_at DATETIME NULL,
    received_email_sent_at DATETIME NULL,
    admin_received_email_sent_at DATETIME NULL,
    idea_approved_email_sent_at DATETIME NULL,
    idea_rejected_email_sent_at DATETIME NULL,
    idea_changes_email_sent_at DATETIME NULL,
    execution_received_email_sent_at DATETIME NULL,
    execution_admin_email_sent_at DATETIME NULL,
    execution_changes_email_sent_at DATETIME NULL,
    execution_rejected_email_sent_at DATETIME NULL,
    reward_email_sent_at DATETIME NULL,
    reward_admin_email_sent_at DATETIME NULL,
    shipped_email_sent_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_promo_public (public_application_number),
    UNIQUE KEY uq_promo_reward_order (reward_order_id),
    INDEX idx_promo_email (email),
    INDEX idx_promo_status (status),
    INDEX idx_promo_created (created_at),
    INDEX idx_promo_token (execution_token_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS promo_status_history (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    promo_application_id INT UNSIGNED NOT NULL,
    old_status VARCHAR(40) NULL,
    new_status VARCHAR(40) NOT NULL,
    actor VARCHAR(32) NOT NULL,
    actor_id INT UNSIGNED NULL,
    public_note TEXT NULL,
    internal_note TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_psh_promo (promo_application_id),
    INDEX idx_psh_created (created_at),
    CONSTRAINT fk_psh_promo FOREIGN KEY (promo_application_id)
        REFERENCES promo_applications(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Mollie webhook / payment events (idempotent)
CREATE TABLE IF NOT EXISTS payment_events (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    mollie_payment_id VARCHAR(64) NOT NULL,
    event_key VARCHAR(128) NOT NULL,
    order_id INT UNSIGNED NULL,
    mollie_status VARCHAR(40) NULL,
    processed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_payment_event (event_key),
    INDEX idx_pe_mollie (mollie_payment_id),
    INDEX idx_pe_order (order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Algemene mail-idempotency (extra vangnet)
CREATE TABLE IF NOT EXISTS outbound_mail_log (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    mail_key VARCHAR(160) NOT NULL,
    recipient VARCHAR(255) NOT NULL,
    subject VARCHAR(255) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_mail_key (mail_key),
    INDEX idx_mail_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Rate limiting
CREATE TABLE IF NOT EXISTS rate_limits (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    rate_key VARCHAR(128) NOT NULL,
    hits INT UNSIGNED NOT NULL DEFAULT 1,
    window_start DATETIME NOT NULL,
    UNIQUE KEY uq_rate_key (rate_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
