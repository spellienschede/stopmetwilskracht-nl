<?php
declare(strict_types=1);

namespace Grippartner;

final class Validator
{
    /** @return array<string,string> */
    public static function order(array $input): array
    {
        $errors = [];
        $required = [
            'first_name' => 'Voornaam',
            'last_name' => 'Achternaam',
            'email' => 'E-mailadres',
            'street' => 'Straat',
            'house_number' => 'Huisnummer',
            'postal_code' => 'Postcode',
            'city' => 'Woonplaats',
            'country' => 'Land',
        ];
        foreach ($required as $key => $label) {
            if (trim((string) ($input[$key] ?? '')) === '') {
                $errors[$key] = $label . ' is verplicht.';
            }
        }
        $email = strtolower(trim((string) ($input['email'] ?? '')));
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Vul een geldig e-mailadres in.';
        }
        $qty = (int) ($input['quantity'] ?? 0);
        if ($qty < 1 || $qty > 50) {
            $errors['quantity'] = 'Kies tussen 1 en 50 exemplaren.';
        }
        $country = strtoupper(trim((string) ($input['country'] ?? '')));
        if ($country !== '' && !preg_match('/^[A-Z]{2}$/', $country)) {
            $errors['country'] = 'Kies een geldig land.';
        }
        foreach (['first_name', 'last_name', 'city'] as $f) {
            if (mb_strlen(trim((string) ($input[$f] ?? ''))) > 100) {
                $errors[$f] = 'Dit veld is te lang.';
            }
        }
        if (mb_strlen(trim((string) ($input['street'] ?? ''))) > 150) {
            $errors['street'] = 'Straat is te lang.';
        }
        return $errors;
    }

    /** @return array<string,string> */
    public static function promo(array $input): array
    {
        $errors = [];
        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '') {
            $name = trim((string) ($input['first_name'] ?? ''));
        }
        if ($name === '') {
            $errors['name'] = 'Naam is verplicht.';
        } elseif (mb_strlen($name) > 160) {
            $errors['name'] = 'Naam is te lang.';
        }

        $email = strtolower(trim((string) ($input['email'] ?? '')));
        if ($email === '') {
            $errors['email'] = 'E-mailadres is verplicht.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Vul een geldig e-mailadres in.';
        }

        $idea = trim((string) ($input['idea_description'] ?? ''));
        if ($idea === '') {
            $errors['idea_description'] = 'Vertel kort wat je hebt gedaan.';
        } elseif (mb_strlen($idea) < 15) {
            $errors['idea_description'] = 'Schrijf iets meer over je promo (minimaal 15 tekens).';
        } elseif (mb_strlen($idea) > 5000) {
            $errors['idea_description'] = 'Je omschrijving is te lang (max. 5000 tekens).';
        }

        foreach (['street' => 'Straat', 'house_number' => 'Huisnummer', 'postal_code' => 'Postcode', 'city' => 'Woonplaats'] as $key => $label) {
            if (trim((string) ($input[$key] ?? '')) === '') {
                $errors[$key] = $label . ' is verplicht.';
            }
        }

        return $errors;
    }

    public static function normalizeOrderInput(array $input): array
    {
        return [
            'first_name' => self::clean(trim((string) ($input['first_name'] ?? '')), 100),
            'last_name' => self::clean(trim((string) ($input['last_name'] ?? '')), 100),
            'email' => strtolower(trim((string) ($input['email'] ?? ''))),
            'street' => self::clean(trim((string) ($input['street'] ?? '')), 150),
            'house_number' => self::clean(trim((string) ($input['house_number'] ?? '')), 20),
            'house_addition' => self::clean(trim((string) ($input['house_addition'] ?? '')), 20) ?: null,
            'postal_code' => self::clean(trim((string) ($input['postal_code'] ?? '')), 20),
            'city' => self::clean(trim((string) ($input['city'] ?? '')), 100),
            'country' => strtoupper(self::clean(trim((string) ($input['country'] ?? Config::string('DEFAULT_COUNTRY', 'NL'))), 2)),
            'quantity' => max(1, min(50, (int) ($input['quantity'] ?? 1))),
            'marketing_consent' => false,
            'terms' => true,
        ];
    }

    public static function normalizePromoInput(array $input): array
    {
        $name = self::clean(trim((string) ($input['name'] ?? '')), 160);
        if ($name === '') {
            $first = self::clean(trim((string) ($input['first_name'] ?? '')), 100);
            $last = self::clean(trim((string) ($input['last_name'] ?? '')), 100);
            $name = trim($first . ' ' . $last);
        }

        $parts = preg_split('/\s+/', $name, 2) ?: [];
        $firstName = $parts[0] ?? '';
        $lastName = $parts[1] ?? '';
        if ($lastName === '') {
            $lastName = '-';
        }

        return [
            'name' => $name,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => strtolower(trim((string) ($input['email'] ?? ''))),
            'street' => self::clean(trim((string) ($input['street'] ?? '')), 150),
            'house_number' => self::clean(trim((string) ($input['house_number'] ?? '')), 20),
            'house_addition' => self::clean(trim((string) ($input['house_addition'] ?? '')), 20) ?: null,
            'postal_code' => self::clean(trim((string) ($input['postal_code'] ?? '')), 20),
            'city' => self::clean(trim((string) ($input['city'] ?? '')), 100),
            'country' => strtoupper(self::clean(trim((string) ($input['country'] ?? Config::string('DEFAULT_COUNTRY', 'NL'))), 2)),
            'idea_description' => self::clean(trim((string) ($input['idea_description'] ?? '')), 5000),
            'promo_link' => self::clean(trim((string) ($input['promo_link'] ?? '')), 5000),
            'audience_reach' => '',
            'proposed_planning' => '',
            'marketing_consent' => false,
        ];
    }

    /** @return array<string,string> */
    public static function mediaRequest(array $input): array
    {
        $errors = [];
        if (trim((string) ($input['name'] ?? '')) === '') {
            $errors['name'] = 'Naam is verplicht.';
        } elseif (mb_strlen(trim((string) $input['name'])) > 160) {
            $errors['name'] = 'Naam is te lang.';
        }
        if (trim((string) ($input['organization'] ?? '')) === '') {
            $errors['organization'] = 'Organisatie of medium is verplicht.';
        } elseif (mb_strlen(trim((string) $input['organization'])) > 200) {
            $errors['organization'] = 'Organisatie is te lang.';
        }
        $email = strtolower(trim((string) ($input['email'] ?? '')));
        if ($email === '') {
            $errors['email'] = 'E-mailadres is verplicht.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Vul een geldig e-mailadres in.';
        }
        $type = (string) ($input['request_type'] ?? '');
        if ($type === '' || !isset(MediaKit::REQUEST_TYPES[$type])) {
            $errors['request_type'] = 'Kies een soort aanvraag.';
        }
        $message = trim((string) ($input['message'] ?? ''));
        if ($message === '') {
            $errors['message'] = 'Toelichting is verplicht.';
        } elseif (mb_strlen($message) < 20) {
            $errors['message'] = 'Maak je toelichting concreter (minimaal 20 tekens).';
        } elseif (mb_strlen($message) > 5000) {
            $errors['message'] = 'Toelichting is te lang.';
        }
        $url = trim((string) ($input['channel_url'] ?? ''));
        if ($url !== '' && mb_strlen($url) > 500) {
            $errors['channel_url'] = 'Website of kanaal is te lang.';
        }
        if ($url !== '' && !preg_match('#^(https?://|www\.)#i', $url) && !str_contains($url, '.')) {
            // soft: allow handles like @name without forcing URL
        }
        if (mb_strlen(trim((string) ($input['preferred_date'] ?? ''))) > 40) {
            $errors['preferred_date'] = 'Datumveld is te lang.';
        }
        if (mb_strlen(trim((string) ($input['audience_reach'] ?? ''))) > 1000) {
            $errors['audience_reach'] = 'Bereik is te lang.';
        }
        return $errors;
    }

    /** @return array<string,mixed> */
    public static function normalizeMediaRequestInput(array $input): array
    {
        return [
            'name' => self::clean(trim((string) ($input['name'] ?? '')), 160),
            'organization' => self::clean(trim((string) ($input['organization'] ?? '')), 200),
            'email' => strtolower(trim((string) ($input['email'] ?? ''))),
            'channel_url' => self::clean(trim((string) ($input['channel_url'] ?? '')), 500),
            'request_type' => self::clean(trim((string) ($input['request_type'] ?? '')), 40),
            'message' => self::clean(trim((string) ($input['message'] ?? '')), 5000),
            'preferred_date' => self::clean(trim((string) ($input['preferred_date'] ?? '')), 40),
            'audience_reach' => self::clean(trim((string) ($input['audience_reach'] ?? '')), 1000),
            'privacy' => !empty($input['privacy']),
        ];
    }

    /** @return array<string,string> */
    public static function presentationSignup(array $input): array
    {
        $errors = [];
        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '') {
            $errors['name'] = 'Naam is verplicht.';
        } elseif (mb_strlen($name) > 160) {
            $errors['name'] = 'Naam is te lang.';
        }
        $email = strtolower(trim((string) ($input['email'] ?? '')));
        if ($email === '') {
            $errors['email'] = 'E-mailadres is verplicht.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Vul een geldig e-mailadres in.';
        }
        if (mb_strlen(trim((string) ($input['notes'] ?? ''))) > 2000) {
            $errors['notes'] = 'Opmerking is te lang.';
        }
        return $errors;
    }

    /** @return array{name:string,email:string,notes:string} */
    public static function normalizePresentationSignupInput(array $input): array
    {
        return [
            'name' => self::clean(trim((string) ($input['name'] ?? '')), 160),
            'email' => strtolower(trim((string) ($input['email'] ?? ''))),
            'notes' => self::clean(trim((string) ($input['notes'] ?? '')), 2000),
        ];
    }

    /** @return array<string,string> */
    public static function sessionSignup(array $input): array
    {
        $errors = [];
        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '') {
            $errors['name'] = 'Naam is verplicht.';
        } elseif (mb_strlen($name) > 160) {
            $errors['name'] = 'Naam is te lang.';
        }
        $email = strtolower(trim((string) ($input['email'] ?? '')));
        if ($email === '') {
            $errors['email'] = 'E-mailadres is verplicht.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Vul een geldig e-mailadres in.';
        }
        return $errors;
    }

    /** @return array{name:string,email:string} */
    public static function normalizeSessionSignupInput(array $input): array
    {
        return [
            'name' => self::clean(trim((string) ($input['name'] ?? '')), 160),
            'email' => strtolower(trim((string) ($input['email'] ?? ''))),
        ];
    }

    /**
     * @return array{
     *   slug:string,title:string,hosts:string,intro:string,
     *   starts_at:string,ends_at:string,meeting_url:string,
     *   signup_open:bool,is_published:bool,
     *   host1_name:string,host1_role:string,host1_photo:string,
     *   host2_name:string,host2_role:string,host2_photo:string
     * }
     */
    public static function normalizeSessionAdminInput(array $input): array
    {
        $date = trim((string) ($input['date'] ?? ''));
        $startTime = trim((string) ($input['start_time'] ?? '19:30'));
        $endTime = trim((string) ($input['end_time'] ?? ''));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $date = '';
        }
        if (!preg_match('/^\d{1,2}:\d{2}$/', $startTime)) {
            $startTime = '19:30';
        }
        if ($endTime !== '' && !preg_match('/^\d{1,2}:\d{2}$/', $endTime)) {
            $endTime = '';
        }
        $startParts = array_map('intval', explode(':', $startTime));
        $startsAt = $date !== ''
            ? sprintf('%s %02d:%02d:00', $date, $startParts[0], $startParts[1] ?? 0)
            : '';
        $endsAt = '';
        if ($date !== '' && $endTime !== '') {
            $endParts = array_map('intval', explode(':', $endTime));
            $endsAt = sprintf('%s %02d:%02d:00', $date, $endParts[0], $endParts[1] ?? 0);
        }

        return [
            'slug' => self::clean(trim((string) ($input['slug'] ?? '')), 80),
            'title' => self::clean(trim((string) ($input['title'] ?? '')), 255),
            'hosts' => self::clean(trim((string) ($input['hosts'] ?? '')), 255),
            'intro' => self::clean(trim((string) ($input['intro'] ?? '')), 4000),
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'meeting_url' => self::clean(trim((string) ($input['meeting_url'] ?? '')), 500),
            'signup_open' => !empty($input['signup_open']),
            'is_published' => !empty($input['is_published']),
            'host1_name' => self::clean(trim((string) ($input['host1_name'] ?? '')), 120),
            'host1_role' => self::clean(trim((string) ($input['host1_role'] ?? '')), 160),
            'host1_photo' => self::clean(trim((string) ($input['host1_photo'] ?? '')), 255),
            'host2_name' => self::clean(trim((string) ($input['host2_name'] ?? '')), 120),
            'host2_role' => self::clean(trim((string) ($input['host2_role'] ?? '')), 160),
            'host2_photo' => self::clean(trim((string) ($input['host2_photo'] ?? '')), 255),
        ];
    }

    /** @return array<string,string> */
    public static function sessionAdmin(array $input): array
    {
        $errors = [];
        $data = self::normalizeSessionAdminInput($input);
        if ($data['title'] === '') {
            $errors['title'] = 'Titel is verplicht.';
        }
        if ($data['starts_at'] === '') {
            $errors['date'] = 'Datum en starttijd zijn verplicht.';
        }
        if ($data['meeting_url'] !== '' && !filter_var($data['meeting_url'], FILTER_VALIDATE_URL)) {
            $errors['meeting_url'] = 'Vul een geldige URL in (inclusief https://).';
        }
        return $errors;
    }

    private static function clean(string $value, int $max): string
    {
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value) ?? $value;
        if (mb_strlen($value) > $max) {
            $value = mb_substr($value, 0, $max);
        }
        return $value;
    }
}
