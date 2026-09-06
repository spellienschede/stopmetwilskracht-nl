<?php
declare(strict_types=1);

namespace Grippartner;

final class View
{
    /** @param array<string,mixed> $data */
    public static function render(string $template, array $data = [], ?string $layout = 'layout'): void
    {
        extract($data, EXTR_SKIP);
        $contentTemplate = app_path('templates/' . $template . '.php');
        if (!is_readable($contentTemplate)) {
            http_response_code(500);
            echo 'Template ontbreekt.';
            return;
        }

        if ($layout === null) {
            require $contentTemplate;
            return;
        }

        ob_start();
        require $contentTemplate;
        $content = ob_get_clean() ?: '';
        require app_path('templates/' . $layout . '.php');
    }

    public static function countries(): array
    {
        return [
            'NL' => 'Nederland',
            'BE' => 'België',
            'DE' => 'Duitsland',
            'FR' => 'Frankrijk',
            'GB' => 'Verenigd Koninkrijk',
            'AT' => 'Oostenrijk',
            'CH' => 'Zwitserland',
            'LU' => 'Luxemburg',
            'ES' => 'Spanje',
            'IT' => 'Italië',
            'PT' => 'Portugal',
            'IE' => 'Ierland',
            'SE' => 'Zweden',
            'NO' => 'Noorwegen',
            'DK' => 'Denemarken',
            'FI' => 'Finland',
            'PL' => 'Polen',
            'US' => 'Verenigde Staten',
            'CA' => 'Canada',
            'AU' => 'Australië',
            'OTHER' => 'Ander land',
        ];
    }
}
