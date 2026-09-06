-- Online sessies (meerdere events) + aanmeldingen per sessie
-- Herhaalbaar uitvoerbaar.

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS live_sessions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(80) NOT NULL,
    title VARCHAR(255) NOT NULL,
    hosts VARCHAR(255) NOT NULL DEFAULT '',
    intro TEXT NULL,
    starts_at DATETIME NOT NULL,
    ends_at DATETIME NULL,
    meeting_url VARCHAR(500) NULL,
    signup_open TINYINT(1) NOT NULL DEFAULT 1,
    is_published TINYINT(1) NOT NULL DEFAULT 1,
    host1_name VARCHAR(120) NULL,
    host1_role VARCHAR(160) NULL,
    host1_photo VARCHAR(255) NULL,
    host2_name VARCHAR(120) NULL,
    host2_role VARCHAR(160) NULL,
    host2_photo VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_live_sessions_slug (slug),
    INDEX idx_live_sessions_starts (starts_at),
    INDEX idx_live_sessions_published (is_published)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS live_session_signups (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    session_id INT UNSIGNED NOT NULL,
    public_signup_number VARCHAR(32) NOT NULL,
    name VARCHAR(160) NOT NULL,
    email VARCHAR(255) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_lss_public (public_signup_number),
    UNIQUE KEY uq_lss_email_session (session_id, email),
    INDEX idx_lss_session (session_id),
    INDEX idx_lss_created (created_at),
    CONSTRAINT fk_lss_session FOREIGN KEY (session_id) REFERENCES live_sessions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed: eerste sessie 14 september 2026 (alleen als slug nog niet bestaat)
INSERT INTO live_sessions (
    slug, title, hosts, intro, starts_at, ends_at,
    signup_open, is_published,
    host1_name, host1_role, host1_photo,
    host2_name, host2_role, host2_photo
)
SELECT
    '3-geheimen',
    '3 geheimen over controle op je leven',
    'Bas Oude Luttikhuis en Korné Pot',
    'Je denkt dat je controle hebt — tot je merkt hoeveel er langs je heen gaat. In 30 minuten delen Bas en Korné drie geheimen over echte controle op je leven. Kort, concreet, gratis. Meld je aan; de Zoom-link volgt per mail.',
    '2026-09-14 19:30:00',
    '2026-09-14 20:00:00',
    1,
    1,
    'Bas Oude Luttikhuis',
    'Ondernemer & coach',
    '/assets/img/sessions/bas-oude-luttikhuis.jpg',
    'Korné Pot',
    'Auteur Stop met wilskracht',
    '/assets/img/sessions/korne-pot-adidas.jpg'
WHERE NOT EXISTS (
    SELECT 1 FROM live_sessions WHERE slug = '3-geheimen'
);
