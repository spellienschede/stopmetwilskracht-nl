<?php
declare(strict_types=1);

namespace Grippartner;

/**
 * Mediakit-inhoud, assets en feiten — één bron voor /media.
 */
final class MediaKit
{
    /** @var array<string,string> */
    public const REQUEST_TYPES = [
        'interview' => 'Interview',
        'podcast' => 'Podcast',
        'artikel' => 'Artikel',
        'recensie' => 'Recensie-exemplaar',
        'lezing' => 'Lezing of evenement',
        'promotie' => 'Promotiesamenwerking',
        'anders' => 'Anders',
    ];

    /** @return list<array{id:string,label:string}> */
    public static function pageNav(): array
    {
        return [
            ['id' => 'intro', 'label' => 'Intro'],
            ['id' => 'feiten', 'label' => 'Feiten'],
            ['id' => 'teksten', 'label' => 'Teksten'],
            ['id' => 'kern', 'label' => 'Kern'],
            ['id' => 'auteur', 'label' => 'Auteur'],
            ['id' => 'interviews', 'label' => 'Interviews'],
            ['id' => 'citaten', 'label' => 'Citaten'],
            ['id' => 'beeld', 'label' => 'Beeld'],
            ['id' => 'social', 'label' => 'Social'],
            ['id' => 'factsheet', 'label' => 'Factsheet'],
            ['id' => 'contact', 'label' => 'Contact'],
            ['id' => 'promo', 'label' => 'Gratis exemplaar'],
        ];
    }

    public static function contactEmail(): string
    {
        $email = trim(Config::string('MEDIA_CONTACT_EMAIL', ''));
        return $email !== '' ? $email : Config::string('ADMIN_NOTIFICATION_EMAIL', 'info@kornepot.nl');
    }

    public static function usageRights(): string
    {
        $manifest = self::rawManifest();
        $text = trim((string) ($manifest['usage_rights'] ?? ''));
        if ($text !== '') {
            return $text;
        }
        return 'Dit materiaal mag redactioneel worden gebruikt in publicaties over Grippartner, Korne Pot of een gerelateerde bijeenkomst of samenwerking. Vermeld fotograaf of maker wanneer die bij het bestand staat aangegeven. Voor commercieel gebruik of ander gebruik: neem contact op via ' . self::contactEmail() . '.';
    }

    /** @return array<string,mixed> */
    private static function rawManifest(): array
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }
        $path = dirname(__DIR__) . '/config/media-manifest.php';
        if (!is_readable($path)) {
            return $cache = ['usage_rights' => '', 'items' => []];
        }
        /** @var mixed $data */
        $data = require $path;
        return $cache = is_array($data) ? $data : ['usage_rights' => '', 'items' => []];
    }

    /**
     * @return list<array<string,mixed>>
     */
    public static function assets(): array
    {
        $items = self::rawManifest()['items'] ?? [];
        if (!is_array($items)) {
            return [];
        }
        $out = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $path = (string) ($item['path'] ?? '');
            $flag = !empty($item['available']);
            $absolute = $path !== '' ? app_path(ltrim($path, '/')) : '';
            $exists = $absolute !== '' && is_file($absolute) && is_readable($absolute);
            $item['file_exists'] = $exists;
            $item['downloadable'] = $flag && $exists;
            if ($exists && empty($item['size_label'])) {
                $bytes = filesize($absolute);
                if ($bytes !== false) {
                    $item['size_label'] = self::formatBytes((int) $bytes);
                }
            }
            // Config overrides for key assets
            $id = (string) ($item['id'] ?? '');
            $overrideKey = match ($id) {
                'cover-high' => 'BOOK_COVER_HIGH_RES',
                'cover-web' => 'BOOK_COVER_WEB',
                'author-portrait' => 'AUTHOR_PHOTO_PORTRAIT',
                'author-landscape' => 'AUTHOR_PHOTO_LANDSCAPE',
                'author-square' => 'AUTHOR_PHOTO_SQUARE',
                'kit-zip' => 'MEDIA_KIT_ZIP',
                default => '',
            };
            if ($overrideKey !== '') {
                $override = trim(Config::string($overrideKey, ''));
                if ($override !== '') {
                    $item['path'] = $override;
                    $abs2 = app_path(ltrim($override, '/'));
                    $item['file_exists'] = is_file($abs2);
                    $item['downloadable'] = $item['file_exists'];
                    $item['available'] = true;
                    if ($item['file_exists']) {
                        $bytes = filesize($abs2);
                        if ($bytes !== false) {
                            $item['size_label'] = self::formatBytes((int) $bytes);
                        }
                    }
                }
            }
            $out[] = $item;
        }
        return $out;
    }

    /** @return list<array<string,mixed>> */
    public static function assetsByCategory(string $category): array
    {
        return array_values(array_filter(
            self::assets(),
            static fn (array $a): bool => ($a['category'] ?? '') === $category
        ));
    }

    public static function findAsset(string $id): ?array
    {
        foreach (self::assets() as $asset) {
            if (($asset['id'] ?? '') === $id) {
                return $asset;
            }
        }
        return null;
    }

    private static function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes . ' B';
        }
        if ($bytes < 1024 * 1024) {
            return round($bytes / 1024, 1) . ' KB';
        }
        return round($bytes / (1024 * 1024), 1) . ' MB';
    }

    /** @return list<array{label:string,value:string}> */
    public static function factRows(): array
    {
        $rows = [
            ['label' => 'Titel', 'value' => Config::string('BOOK_TITLE')],
            ['label' => 'Ondertitel', 'value' => Config::string('BOOK_SUBTITLE')],
            ['label' => 'Auteur', 'value' => Config::string('BOOK_AUTHOR')],
            ['label' => 'Publicatiedatum', 'value' => Config::formatReleaseDate()],
            ['label' => 'Prijs', 'value' => Config::priceFormatted() . ' inclusief btw en verzending'],
            ['label' => 'Taal', 'value' => 'Nederlands'],
            ['label' => 'Website', 'value' => 'www.grippartner.nl'],
            ['label' => 'Contact', 'value' => self::contactEmail()],
        ];
        $optional = [
            ['label' => 'Uitvoering', 'key' => 'BOOK_FORMAT'],
            ['label' => 'Aantal pagina’s', 'key' => 'BOOK_PAGE_COUNT'],
            ['label' => 'ISBN', 'key' => 'BOOK_ISBN'],
            ['label' => 'Uitgever', 'key' => 'BOOK_PUBLISHER'],
        ];
        foreach ($optional as $opt) {
            $v = trim(Config::string($opt['key'], ''));
            if ($v !== '') {
                $rows[] = ['label' => $opt['label'], 'value' => $v];
            }
        }
        return $rows;
    }

    public static function elevatorPitch(): string
    {
        return 'Veel mensen weten al wat zij zouden willen veranderen. Het probleem is zelden weten of willen — het is doen. Grippartner van Korne Pot introduceert een eenvoudige methode: twee gelijkwaardige mensen, één gedeeld document en wekelijks dertig minuten aandacht voor wat werkelijk telt.';
    }

    public static function shortDescription(): string
    {
        return 'Grippartner van Korne Pot gaat over de afstand tussen goede voornemens en werkelijk gedrag. Niemand zoekt een grippartner, maar iedereen heeft er één nodig: iemand die je wekelijks, met één gedeeld document, helpt meer grip te krijgen op wat voor jou belangrijk is — gezin, gezondheid, geluk, relaties, ontwikkeling en werk. De aanpak is nuchter: twee gelijkwaardige mensen, dertig minuten per week, minimaal drie maanden serieus proberen. Geen nieuw dashboard, wel een mens die meekijkt.';
    }

    public static function longDescription(): string
    {
        return "Je weet vaak al verrassend goed wat verstandig zou zijn. Toch gebeurt er iets tussen zondagavond en dinsdagmiddag. Belangrijke dingen verdwijnen onder urgente drukte. De afstand zit zelden in kennis of motivatie — die zit tussen weten en doen.\n\n"
            . "Grippartner van Korne Pot beschrijft een verrassend eenvoudige manier om die afstand kleiner te maken. Een grippartner is iemand die je op wekelijkse basis, met een gedeeld document, helpt meer grip te krijgen op de onderdelen van je leven die voor jou belangrijk zijn.\n\n"
            . "De methode is licht: twee gelijkwaardige mensen, één scorebord, ongeveer dertig minuten per week. Jullie kijken terug op wat er gebeurde en vooruit op wat werkelijk aandacht moet krijgen. Geen coach–cliënt-relatie, geen zwaar systeem. Wel zichtbaar en bespreekbaar gedrag.\n\n"
            . "Het boek gaat niet alleen over productiviteit. Het gaat over aandacht voor gezin, gezondheid, geluk, relaties, persoonlijke ontwikkeling én werk. Korne werkt sinds 2020 met zijn grippartner Paul en heeft tientallen mensen geholpen om met een eigen grippartner te beginnen.\n\n"
            . 'Na het lezen kun je één persoon benaderen, een eenvoudig document delen en de samenwerking minimaal drie maanden serieus testen. Niet omdat je meer moet doen — maar omdat je wilt vasthouden wat je écht belangrijk vindt.';
    }

    public static function bioShort(): string
    {
        return 'Korne Pot schrijft vanuit eigen ondernemerservaring over de afstand tussen weten en doen. Sinds 2020 werkt hij wekelijks met zijn grippartner Paul. Hij hielp tientallen mensen om met een eigen grippartner te beginnen. Grippartner verschijnt ' . Config::formatReleaseDate() . '.';
    }

    public static function bioLong(): string
    {
        return "Korne Pot schrijft vanuit eigen ondernemerservaring. Hij merkte bij zichzelf — en later bij anderen — dat weten wat belangrijk is zelden het echte knelpunt is. Het knelpunt zit in doen: in weken waarin urgente zaken de aandacht opslokken die bedoeld was voor gezin, gezondheid, relaties of ontwikkeling.\n\n"
            . "Sinds 2020 werkt Korne wekelijks met zijn grippartner Paul. Die eenvoudige samenwerking — één gedeeld document, ongeveer dertig minuten per week — werd de basis voor het boek Grippartner. Daarnaast hielp hij tientallen mensen om met een eigen grippartner te beginnen.\n\n"
            . 'Met Grippartner wil hij een nuchtere, toepasbare methode delen: geen goeroetaal, geen nieuw dashboard, wel één mens die meekijkt op wat werkelijk telt. Het boek verschijnt ' . Config::formatReleaseDate() . ' via www.grippartner.nl.';
    }

    /** @return list<array{title:string,note:string}> */
    public static function interviewTopics(): array
    {
        return [
            ['title' => 'Waarom weten wat goed voor je is zelden genoeg is', 'note' => 'Over de kloof tussen inzicht en gedrag.'],
            ['title' => 'De afstand tussen goede voornemens en werkelijk gedrag', 'note' => 'Wat er gebeurt tussen zondagavond en dinsdagmiddag.'],
            ['title' => 'Waarom niemand een grippartner zoekt', 'note' => 'Over een behoefte zonder zoekterm.'],
            ['title' => 'Wat een grippartner anders maakt dan een coach', 'note' => 'Gelijkwaardigheid versus hulpverlening.'],
            ['title' => 'Hoe sociale verantwoordelijkheid gedrag kan veranderen', 'note' => 'Waarom “iemand die meekijkt” werkt.'],
            ['title' => 'Waarom één gedeeld document zoveel verschil kan maken', 'note' => 'Het scorebord als aandachtshulp, niet als doel.'],
            ['title' => 'Dertig minuten per week die werk én privé kunnen beïnvloeden', 'note' => 'De minimale ritmiek van de methode.'],
            ['title' => 'Waarom belangrijke zaken verliezen van urgente zaken', 'note' => 'Aandacht onder druk.'],
            ['title' => 'Hoe je een geschikte grippartner kiest', 'note' => 'Praktische criteria zonder perfectie.'],
            ['title' => 'Hoe je iemand vraagt om jouw grippartner te worden', 'note' => 'De lastigste stap voor veel mensen.'],
            ['title' => 'Wanneer een grippartnerschap niet werkt', 'note' => 'Grenzen, signalen en stoppen of wisselen.'],
            ['title' => 'Waarom grip niet hetzelfde is als zoveel mogelijk doen', 'note' => 'Focus versus drukte.'],
            ['title' => 'Wat ondernemers kunnen leren van gelijkwaardige verantwoording', 'note' => 'Zonder coachtraject of zwaar dashboard.'],
            ['title' => 'Hoe je goede gewoontes drie maanden serieus test', 'note' => 'Waarom duur belangrijker is dan enthousiasme.'],
        ];
    }

    /** @return list<string> */
    public static function sampleQuestions(): array
    {
        return [
            'Wanneer ontdekte je dat kennis niet het echte probleem was?',
            'Wat zag je bij jezelf gebeuren voordat je met Paul begon?',
            'Waarom noemen mensen dit niet gewoon een accountabilitypartner?',
            'Wat maakt iemand een goede grippartner — en wat juist niet?',
            'Waarom moet de relatie gelijkwaardig zijn?',
            'Wat staat er concreet in het gedeelde document?',
            'Wat bespreek je in dertig minuten, en wat laat je bewust weg?',
            'Waarom adviseer je om het minimaal drie maanden te proberen?',
            'Kan een goede vriend ook een grippartner zijn, of werkt dat juist slecht?',
            'Wat gebeurt er wanneer één van beiden afspraken blijft missen?',
            'Waar ligt de grens tussen een grippartner en professionele hulp?',
            'Wanneer moet je stoppen of wisselen van partner?',
            'Welke delen van je leven kun je met een grippartner bespreken — en welke niet?',
            'Welke weerstand voelen mensen bij het vragen van een partner?',
            'Hoe voorkom je dat het scorebord een nieuw prestatiemiddel wordt?',
            'Wat verandert er in werk wanneer privé weer zichtbaar wordt?',
            'Hoe reageert iemand die “geen tijd” heeft voor dertig minuten per week?',
            'Wat hoop je dat een lezer direct na het laatste hoofdstuk doet?',
        ];
    }

    /**
     * @return list<array{text:string,kind:string,label:string}>
     * kind: campaign | summary (geen manuscript-citaten claimen zonder bron)
     */
    public static function quotes(): array
    {
        return [
            [
                'text' => 'Niemand is op zoek naar een grippartner, maar iedereen heeft er één nodig.',
                'kind' => 'campaign',
                'label' => 'Campagnezin',
            ],
            [
                'text' => 'De afstand ontstaat tussen weten en doen.',
                'kind' => 'summary',
                'label' => 'Samenvattingszin',
            ],
            [
                'text' => 'Je weet vaak al verrassend goed wat verstandig zou zijn.',
                'kind' => 'summary',
                'label' => 'Samenvattingszin',
            ],
            [
                'text' => 'Een grippartner helpt je aandacht te geven aan wat werkelijk telt.',
                'kind' => 'summary',
                'label' => 'Samenvattingszin',
            ],
            [
                'text' => 'Het scorebord is geen doel. Het richt je aandacht op wat waardevol is.',
                'kind' => 'summary',
                'label' => 'Samenvattingszin',
            ],
            [
                'text' => 'Waarom niemand op zoek is naar een grippartner, maar iedereen er één nodig heeft.',
                'kind' => 'campaign',
                'label' => 'Campagnehaak',
            ],
            [
                'text' => 'Een grippartner is iemand die je op wekelijkse basis, met een gedeeld document, helpt meer grip te krijgen op de onderdelen van je leven die voor jou belangrijk zijn.',
                'kind' => 'summary',
                'label' => 'Definitie',
            ],
            [
                'text' => 'Dertig minuten per week voor wat werkelijk telt.',
                'kind' => 'campaign',
                'label' => 'Campagnezin',
            ],
        ];
    }

    /** @return list<string> */
    public static function socialLines(): array
    {
        return [
            'Waarom niemand een grippartner zoekt',
            'De afstand tussen weten en doen',
            'Wie staat er iedere week naast jou?',
            'Je weet het wel. Maar doe je het ook?',
            'Dertig minuten per week voor wat werkelijk telt',
        ];
    }
}
