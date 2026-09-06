<?php
declare(strict_types=1);

namespace Grippartner;

final class PublicId
{
    public static function order(): string
    {
        return 'GP-' . strtoupper(bin2hex(random_bytes(5)));
    }

    public static function promo(): string
    {
        return 'PR-' . strtoupper(bin2hex(random_bytes(5)));
    }

    public static function media(): string
    {
        return 'MD-' . strtoupper(bin2hex(random_bytes(5)));
    }

    public static function presentation(): string
    {
        return 'BP-' . strtoupper(bin2hex(random_bytes(5)));
    }

    public static function session(): string
    {
        return 'SS-' . strtoupper(bin2hex(random_bytes(5)));
    }

    /** @return array{token:string,hash:string} */
    public static function tokenPair(): array
    {
        $token = bin2hex(random_bytes(32));
        return [
            'token' => $token,
            'hash' => hash('sha256', $token),
        ];
    }
}
