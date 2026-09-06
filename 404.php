<?php
declare(strict_types=1);

http_response_code(404);
require __DIR__ . '/src/bootstrap.php';

use Grippartner\Config;

$title = 'Pagina niet gevonden';
$noindex = true;
ob_start();
?>
<section class="section" style="border-top:0;padding-top:2rem">
  <div class="wrap">
    <h1>404</h1>
    <p>Deze pagina bestaat niet.</p>
    <p><a href="/">Naar de homepage</a></p>
  </div>
</section>
<?php
$content = ob_get_clean();
require __DIR__ . '/templates/layout.php';
