# Mediakit – assets toevoegen

## Mapstructuur

Bestanden horen hier (webroot):

```
assets/media/press/
  book-cover/
  author/
  logos/
  illustrations/
  social/
  documents/
```

Directory listing staat uit; PHP-uitvoering is geblokkeerd via `.htaccess`.

## Manifest

Bewerk `config/media-manifest.php`:

1. Plaats het bestand op het pad in `path`
2. Zet `"available" => true`
3. Vul optioneel `creator` / `credit` / `size_label`

Of zet een pad in `config.local.php`:

```php
'BOOK_COVER_HIGH_RES' => '/assets/media/press/book-cover/grippartner-cover-print.jpg',
'BOOK_COVER_WEB' => '/assets/media/press/book-cover/grippartner-cover-web.jpg',
'AUTHOR_PHOTO_PORTRAIT' => '/assets/media/press/author/korne-pot-portrait.jpg',
'AUTHOR_PHOTO_LANDSCAPE' => '/assets/media/press/author/korne-pot-landscape.jpg',
'AUTHOR_PHOTO_SQUARE' => '/assets/media/press/author/korne-pot-square.jpg',
'MEDIA_KIT_ZIP' => '/assets/media/press/documents/grippartner-mediakit.zip',
```

Alleen bestaande + beschikbare bestanden tonen een download. Geen tijdelijke cover als “definitief” presenteren.

## Aanbevolen bestandsnamen

| Asset | Bestandsnaam | Formaat |
|-------|--------------|---------|
| Cover print | `grippartner-cover-print.jpg` | JPG, min. A4 @ 300 dpi |
| Cover web | `grippartner-cover-web.jpg` | JPG ~1200×1800 |
| Mockup | `grippartner-cover-mockup.png` | PNG transparant |
| Auteur liggend | `korne-pot-landscape.jpg` | JPG |
| Auteur staand | `korne-pot-portrait.jpg` | JPG |
| Auteur vierkant | `korne-pot-square.jpg` | JPG 1080×1080 |
| Logo | `grippartner-woordmerk.svg` | SVG of PNG |
| Social 1:1 | `grippartner-social-1080x1080.png` | PNG |
| Social 4:5 | `grippartner-social-1080x1350.png` | PNG |
| Story | `grippartner-social-1080x1920.png` | PNG |
| LinkedIn | `grippartner-social-1200x627.png` | PNG |
| ZIP | `grippartner-mediakit.zip` | ZIP |
| Factsheet PDF | `grippartner-factsheet.pdf` | PDF (optioneel; print via `/media#factsheet` werkt al) |

## Complete ZIP

Bouw lokaal een ZIP met factsheet/teksten/omslag/foto’s/logo/contact/rechten. Upload naar `documents/grippartner-mediakit.zip`, zet `available` true (of `MEDIA_KIT_ZIP`). Tot die tijd toont de site “Complete mediakit volgt”.

## Database

```bash
php scripts/migrate.php
```

Maakt tabel `media_requests` aan.
