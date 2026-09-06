# Grippartner.nl – installatie & oplevering

Nederlandstalige boekverkoop voor **Grippartner** (plain PHP + MySQL + Mollie), voortbouwend op de bestaande nieuwsbrief-site.

## Architectuur (kort)

- Flat PHP, geen framework. Autoload via `src/bootstrap.php`.
- Bestaande nieuwsbrief (`aanmelden.php`, `nieuwsbrief_aanmeldingen`, LNO-tool) blijft werken.
- Nieuw: bestellingen (`book_orders`), promo-workflow (sectie 17), beheer `/admin/book/`, Mollie-webhook.
- Secrets in `config.local.php` of `.env` (niet in Git).

## 1. Serververeisten

- PHP 8.1+ met extensies: `pdo_mysql`, `curl`, `json`, `mbstring`, `openssl`
- MySQL 8 / MariaDB (utf8mb4)
- HTTPS in productie
- Optioneel Composer (`composer.json` noemt `mollie/mollie-api-php`); de site gebruikt standaard de ingebouwde `MollieClient` (cURL) zodat SSL/Composer-problemen op shared hosting geen blocker zijn

## 2. Installatiestappen (FTP-vriendelijk)

**Belangrijk:** `install.php` maakt **geen** MySQL-database aan. Alleen tabellen + admin. Maak in het hostingpanel eerst een lege database (of gebruik de bestaande `grippartner`-DB van de nieuwsbrief) en zet `DB_*` in de config.

1. Upload alle sitebestanden via FTP naar de webroot van `grippartner.nl`.
2. Upload `config.local.production.php` als **`config.local.php`** (of kopieer `config.local.example.php` en vul in):
   - database (`DB_*` controleren in hostingpanel)
   - `APP_BASE_URL` = `https://www.grippartner.nl`
   - `MOLLIE_API_KEY` (live key mag gedeeld worden met andere sites; webhook gaat per betaling mee)
   - Mail: SMTP leeg = PHP `mail()`
   - Dit bestand staat in `.gitignore` en is via `.htaccess` geblokkeerd voor HTTP-download.
3–5. Open in de browser: **`/install.php`**
   - voert de database-migratie uit (tabellen)
   - maakt het eerste adminaccount (of gebruikt `ADMIN_*` uit config)
   - toont/controleert de Mollie-webhook-URL (wordt automatisch meegestuurd bij elke betaling)
6. Verwijder daarna `install.php` via FTP (aanbevolen) en controleer dat `storage/` schrijfbaar is.

Alternatief CLI: `php scripts/migrate.php` + `/admin/book/setup.php`.

## 3. Omgevingsvariabelen / config-sleutels

Zie `.env.example` en `config.local.example.php`. Minimaal:

| Key | Betekenis |
|-----|-----------|
| `APP_BASE_URL` | Publieke basis-URL |
| `DB_*` | Database |
| `BOOK_PRICE_CENTS` | 4900 (normale prijs) |
| `BOOK_PRESALE_END_DATE` | 2026-09-14 (einde pre-orderkorting + bonussen) |
| `BOOK_RELEASE_DATE` | 2026-10-03 (verschijning/verzending) |
| `BOOK_PRESENTATION_DATE` | 2026-10-05 (online boekpresentatie) |
| `BOOK_PRESENTATION_TIME` | 19:30 |
| `MOLLIE_API_KEY` | `test_…` of `live_…` |
| `MOLLIE_WEBHOOK_URL` | Volledige webhook-URL |
| `MAIL_FROM_*` / `ADMIN_NOTIFICATION_EMAIL` | Mail |
| `SMTP_*` | Optioneel, sterk aanbevolen op productie |
| `LEGAL_*` | Bedrijfsnaam, adres, KvK, btw |

**Pre-order vs bestel:** vóór 5 sept 2026 toont de site “Pre-order”; vanaf die datum “Bestel”.

**Cover / auteursfoto:** zet `BOOK_COVER_PATH` / `AUTHOR_PHOTO_PATH` (pad vanaf webroot), of vervang de HTML/CSS-mockup in `#book-cover-component`.

## 4. Mollie test → live

1. Vul `MOLLIE_API_KEY` met een **test** key (`test_…`).
2. Doe een testbetaling met iDEAL-testprofiel van Mollie.
3. Controleer webhook (Mollie Dashboard → Logs) en orderstatus `paid`.
4. Wissel naar **live** key (`live_…`); geen andere codewijziging nodig.
5. Webhook-URL moet publiek bereikbaar zijn via HTTPS (geen localhost zonder tunnel).

De terugkeerpagina (`betaalstatus.php`) markeert **niet** zelf als betaald; alleen de webhook (na heropvragen bij Mollie).

## 5. Mail

- Met SMTP in `config.local.php`: HTML + plain text via SMTP.
- Zonder SMTP op productie: PHP `mail()`.
- Lokaal (`APP_ENV=local`): mails gaan naar `storage/logs/mail-outbox.log` (geen hang op Windows).
- Afzender: Korne Pot / Grippartner → standaard `info@kornepot.nl`.
- Beheerdernotificaties: `info@kornepot.nl`.
- Marketing alleen bij expliciete checkbox → tabel `nieuwsbrief_aanmeldingen` met `consent_source` / `consent_at`.

## 6. Beheer

- Boekbeheer: `/admin/book/` (login, dashboard, orders, promo’s, CSV)
- Oude nieuwsbrief-admin: `/admin-nieuwsbrief.php` (wachtwoord hashen / roteren aanbevolen; stond historisch plaintext in README)
- Robots: admin-paden in `robots.txt` + `noindex` in beheerpagina’s

## 7. Cronjobs

Geen verplicht. Optioneel later: opruimen van verlopen `pending_payment` orders.

## 8. Tests

Automatisch (lokaal uitgevoerd):

```bash
php scripts/test-flows.php
```

Gedekt o.a.: prijs server-side, mailinglijst-toestemming/dubbel, promo-workflow (idee goedkeuren zonder order, uitvoering, één gratis order, dubbele goedkeuring), datum pre-order/bestel, statusovergangen.

Handmatig nog te doen op staging/live:

- Mollie testbetaling (1 en meerdere exemplaren)
- Afgebroken / mislukte / verlopen betaling
- Webhook opnieuw aanbieden
- Terugkeer vóór webhook
- CSRF / ongeautoriseerde admin-URL
- Mobiele weergave
- Promo e-mails in echte inbox (SMTP)

## 9. Juridisch / inhoud

- Bedrijfsgegevens: Het 2e kwadrant (ingevuld in config)
- Privacy & voorwaarden: herroeping 14 dagen, retourporto voor klant
- ISBN / definitieve boekomslag: later
- Mollie live key + webhook controleren na eerste betaling
- Eerste adminwachtwoord veilig bewaren (niet openbaar delen)
- Na install: `install.php` verwijderen via FTP

## 10. Belangrijke URL’s

| URL | Functie |
|-----|---------|
| `/` | Verkooppagina |
| `/bestellen.php` | Bestelformulier |
| `/webhook-mollie.php` | Mollie webhook |
| `/promo.php` | Promo-idee indienen |
| `/promo-uitvoering.php?token=…` | Uitvoering doorgeven |
| `/admin/book/` | Beheer |
| `/tip.html` | Oude nieuwsbrief-landing |
| `/privacy.php` / `/voorwaarden.php` | Juridisch |
