<?php
require_once 'config.php';

$email = trim($_GET['email'] ?? '');

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    die('Ongeldig emailadres.');
}

$pdo = getDbConnection();
if (!$pdo) {
    die('Database connectie mislukt.');
}

try {
    $stmt = $pdo->prepare("UPDATE nieuwsbrief_aanmeldingen SET uitgeschreven = 1, uitgeschreven_datum = NOW() WHERE email = ?");
    $stmt->execute([strtolower($email)]);
    
    if ($stmt->rowCount() > 0) {
        $message = "Je bent succesvol uitgeschreven van de Grippartner nieuwsbrief.\n\n";
        $message .= "Je ontvangt geen wekelijkse tips meer.\n\n";
        $message .= "Wil je je opnieuw aanmelden? Ga naar grippartner.nl";
    } else {
        $message = "Dit emailadres staat niet in onze database.";
    }
} catch (PDOException $e) {
    $message = "Er is een fout opgetreden. Probeer het later opnieuw.";
}
?>
<!doctype html>
<html lang="nl">
<head>
  <!-- Google tag (gtag.js) -->
  <script async src="https://www.googletagmanager.com/gtag/js?id=G-D2TE99HQZQ"></script>
  <script>
    window.dataLayer = window.dataLayer || [];
    function gtag(){dataLayer.push(arguments);}
    gtag('js', new Date());
    gtag('config', 'G-D2TE99HQZQ');
  </script>
  <meta charset="utf-8" />
  <title>Uitgeschreven – Grippartner</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <style>
    body{
      margin:0;
      font-family: system-ui, -apple-system, sans-serif;
      background: #070A12;
      color: #EAF0FF;
      min-height:100vh;
      display:flex;
      align-items:center;
      justify-content:center;
      padding:20px;
    }
    .card{
      max-width:500px;
      background: rgba(255,255,255,.04);
      border: 1px solid rgba(255,255,255,.08);
      border-radius: 16px;
      padding:40px;
      text-align:center;
    }
    h1{font-size:24px; margin-bottom:16px;}
    p{color: #B8C1DA; line-height:1.6;}
    a{color: #22D3EE; text-decoration:none;}
    a:hover{text-decoration:underline;}
  </style>
</head>
<body>
  <div class="card">
    <h1>Uitgeschreven</h1>
    <p><?= htmlspecialchars($message) ?></p>
    <p><a href="index.html">Terug naar Grippartner</a></p>
  </div>
</body>
</html>


