<?php
require_once 'config.php';

// Beveiliging: gebruikersnaam en wachtwoord check
session_start();
$username = $_POST['username'] ?? '';
$password = $_POST['password'] ?? '';
$expected_username = 'grip';
$expected_password = 'hbY814eLaJlhbh';

if (!isset($_SESSION['admin_logged_in'])) {
    if ($username === $expected_username && $password === $expected_password) {
        $_SESSION['admin_logged_in'] = true;
    } else {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $error = 'Onjuiste gebruikersnaam of wachtwoord';
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
          <title>Admin Login – Grippartner</title>
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
              max-width:400px;
              background: rgba(255,255,255,.04);
              border: 1px solid rgba(255,255,255,.08);
              border-radius: 16px;
              padding:40px;
            }
            h1{font-size:24px; margin-bottom:24px; text-align:center;}
            input{
              width:100%;
              padding:12px;
              border-radius:8px;
              border:1px solid rgba(255,255,255,.14);
              background: rgba(255,255,255,.04);
              color: #EAF0FF;
              font-size:16px;
              margin-bottom:16px;
            }
            button{
              width:100%;
              padding:14px;
              border-radius:8px;
              border:0;
              background: linear-gradient(135deg, #7C5CFF, #22D3EE);
              color: #0A0D16;
              font-weight:800;
              font-size:16px;
              cursor:pointer;
            }
            .error{color:#EF4444; margin-bottom:16px; text-align:center;}
          </style>
        </head>
        <body>
          <div class="card">
            <h1>Admin Login</h1>
            <?php if (isset($error)): ?><div class="error"><?= $error ?></div><?php endif; ?>
            <form method="post">
              <input type="text" name="username" placeholder="Gebruikersnaam" required autofocus />
              <input type="password" name="password" placeholder="Wachtwoord" required />
              <button type="submit">Inloggen</button>
            </form>
          </div>
        </body>
        </html>
        <?php
        exit;
    }
}

// Logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: admin-nieuwsbrief.php');
    exit;
}

// Verzend nieuwsbrief — uitgeschakeld: gebruik kornepot.nl/admin
$sent = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verzenden'])) {
    $error = 'Verzenden via grippartner.nl is uitgeschakeld. Gebruik https://www.kornepot.nl/admin-nieuwsbrief.php — '
        . 'daar gaat de mail vanuit info@kornepot.nl met een werkende uitschrijflink.';
}

// Haal statistieken op
$pdo = getDbConnection();
$stats = ['totaal' => 0, 'actief' => 0, 'uitgeschreven' => 0];
$aanmelders = [];
if ($pdo) {
    try {
        $stmt = $pdo->query("SELECT COUNT(*) as totaal FROM nieuwsbrief_aanmeldingen");
        $stats['totaal'] = $stmt->fetch()['totaal'];
        
        $stmt = $pdo->query("SELECT COUNT(*) as actief FROM nieuwsbrief_aanmeldingen WHERE uitgeschreven = 0");
        $stats['actief'] = $stmt->fetch()['actief'];
        
        $stats['uitgeschreven'] = $stats['totaal'] - $stats['actief'];
        
        // Haal alle aanmelders op
        $stmt = $pdo->query("SELECT id, voornaam, achternaam, email, aanmeldmoment, uitgeschreven, uitgeschreven_datum FROM nieuwsbrief_aanmeldingen ORDER BY aanmeldmoment DESC");
        $aanmelders = $stmt->fetchAll();
    } catch (PDOException $e) {
        // Ignore
    }
}

// Bepaal actief tabblad
$active_tab = $_GET['tab'] ?? 'nieuwsbrief';
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
  <title>Nieuwsbrief Verzenden – Grippartner</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <style>
    :root{
      --bg:#070A12;
      --text:#EAF0FF;
      --muted:#B8C1DA;
      --accent:#7C5CFF;
      --accent2:#22D3EE;
      --ok:#22C55E;
      --error:#EF4444;
    }
    *{box-sizing:border-box}
    body{
      margin:0;
      font-family: system-ui, -apple-system, sans-serif;
      background: var(--bg);
      color: var(--text);
      padding:20px;
    }
    .wrap{
      max-width:800px;
      margin:0 auto;
    }
    .card{
      background: rgba(255,255,255,.04);
      border: 1px solid rgba(255,255,255,.08);
      border-radius: 16px;
      padding:32px;
      margin-bottom:24px;
    }
    h1{font-size:28px; margin-bottom:8px;}
    .stats{
      display:grid;
      grid-template-columns: repeat(3, 1fr);
      gap:16px;
      margin:24px 0;
    }
    .stat{
      text-align:center;
      padding:20px;
      background: rgba(255,255,255,.02);
      border-radius:12px;
    }
    .stat strong{
      display:block;
      font-size:32px;
      color: var(--accent2);
      margin-bottom:8px;
    }
    .stat span{
      color: var(--muted);
      font-size:14px;
    }
    label{
      display:block;
      font-weight:700;
      margin:20px 0 8px;
      color: var(--text);
    }
    input[type="text"], textarea{
      width:100%;
      padding:12px;
      border-radius:8px;
      border:1px solid rgba(255,255,255,.14);
      background: rgba(255,255,255,.04);
      color: var(--text);
      font: inherit;
      font-size:16px;
    }
    textarea{
      min-height:200px;
      resize:vertical;
      font-family: monospace;
    }
    button{
      padding:14px 28px;
      border-radius:8px;
      border:0;
      background: linear-gradient(135deg, var(--accent), var(--accent2));
      color: #0A0D16;
      font-weight:800;
      font-size:16px;
      cursor:pointer;
      margin-top:16px;
    }
    button:hover{filter:brightness(1.1);}
    .success{
      background: rgba(34,197,94,.15);
      border: 1px solid rgba(34,197,94,.3);
      padding:16px;
      border-radius:8px;
      color: var(--ok);
      margin-bottom:24px;
    }
    .error{
      background: rgba(239,68,68,.15);
      border: 1px solid rgba(239,68,68,.3);
      padding:16px;
      border-radius:8px;
      color: var(--error);
      margin-bottom:24px;
    }
    .logout{
      text-align:right;
      margin-bottom:16px;
    }
    .logout a{
      color: var(--muted);
      text-decoration:none;
      font-size:14px;
    }
    .logout a:hover{color: var(--text);}
    .tabs{
      display:flex;
      gap:8px;
      margin-bottom:24px;
      border-bottom:1px solid rgba(255,255,255,.08);
    }
    .tab{
      padding:12px 24px;
      background:transparent;
      border:0;
      border-bottom:2px solid transparent;
      color: var(--muted);
      font-size:16px;
      font-weight:600;
      cursor:pointer;
      transition:color .2s, border-color .2s;
    }
    .tab:hover{
      color: var(--text);
    }
    .tab.active{
      color: var(--accent2);
      border-bottom-color: var(--accent2);
    }
    .tab-content{
      display:none;
    }
    .tab-content.active{
      display:block;
    }
    table{
      width:100%;
      border-collapse:collapse;
      margin-top:16px;
    }
    th, td{
      padding:12px;
      text-align:left;
      border-bottom:1px solid rgba(255,255,255,.08);
    }
    th{
      font-weight:700;
      color: var(--accent2);
      font-size:14px;
      text-transform:uppercase;
      letter-spacing:.5px;
    }
    td{
      color: var(--text);
      font-size:14px;
    }
    tr:hover{
      background: rgba(255,255,255,.02);
    }
    .badge{
      display:inline-block;
      padding:4px 10px;
      border-radius:12px;
      font-size:12px;
      font-weight:700;
    }
    .badge.actief{
      background: rgba(34,197,94,.15);
      color: var(--ok);
    }
    .badge.uitgeschreven{
      background: rgba(239,68,68,.15);
      color: var(--error);
    }
    .export-btn{
      display:inline-block;
      padding:10px 20px;
      border-radius:8px;
      border:1px solid rgba(255,255,255,.14);
      background: rgba(255,255,255,.04);
      color: var(--text);
      text-decoration:none;
      font-size:14px;
      font-weight:600;
      margin-bottom:16px;
      transition:background .2s;
    }
    .export-btn:hover{
      background: rgba(255,255,255,.08);
      text-decoration:none;
    }
  </style>
</head>
<body>
  <div class="wrap">
    <div class="logout">
      <a href="?logout=1">Uitloggen</a>
    </div>
    
    <div class="card">
      <h1>Grippartner Admin</h1>
      
      <div class="tabs">
        <button class="tab <?= $active_tab === 'nieuwsbrief' ? 'active' : '' ?>" onclick="showTab('nieuwsbrief')">Nieuwsbrief</button>
        <button class="tab <?= $active_tab === 'aanmelders' ? 'active' : '' ?>" onclick="showTab('aanmelders')">Aanmelders (<?= $stats['totaal'] ?>)</button>
      </div>
      
      <div id="tab-nieuwsbrief" class="tab-content <?= $active_tab === 'nieuwsbrief' ? 'active' : '' ?>">
        <h2 style="font-size:22px; margin-bottom:16px;">Nieuwsbrief Verzenden</h2>
        <p style="color:#FBBF24; background:rgba(251,191,36,.12); border:1px solid rgba(251,191,36,.35); border-radius:12px; padding:12px 14px; margin-bottom:16px;">
          <strong>Verzenden is hier uitgeschakeld.</strong> Stuur de wekelijkse tip alleen via
          <a href="https://www.kornepot.nl/admin-nieuwsbrief.php" style="color:#FDE68A;">kornepot.nl/admin</a>.
          Daar gaat de mail vanuit <strong>info@kornepot.nl</strong> met een klikbare uitschrijflink.
        </p>
      
      <div class="stats">
        <div class="stat">
          <strong><?= $stats['totaal'] ?></strong>
          <span>Totaal</span>
        </div>
        <div class="stat">
          <strong><?= $stats['actief'] ?></strong>
          <span>Actief</span>
        </div>
        <div class="stat">
          <strong><?= $stats['uitgeschreven'] ?></strong>
          <span>Uitgeschreven</span>
        </div>
      </div>
      
      <?php if ($sent): ?>
        <div class="success">✓ <?= $result_message ?></div>
      <?php endif; ?>
      
      <?php if ($error): ?>
        <div class="error">✗ <?= htmlspecialchars($error) ?></div>
      <?php endif; ?>
      
      <form method="post" onsubmit="alert('Verzenden via grippartner.nl is uitgeschakeld. Gebruik kornepot.nl/admin-nieuwsbrief.php'); return false;">
        <label>Onderwerp *</label>
        <input type="text" name="subject" required placeholder="Bijv: Deze week: Focus op wat er toe doet" disabled />
        
        <label>Bericht (Plain Text) *</label>
        <textarea name="message" required placeholder="Verzenden kan alleen via kornepot.nl/admin..." disabled></textarea>
        
        <p><a class="export-btn" href="https://www.kornepot.nl/admin-nieuwsbrief.php">Open kornepot.nl admin →</a></p>
      </form>
      </div>
      
      <div id="tab-aanmelders" class="tab-content <?= $active_tab === 'aanmelders' ? 'active' : '' ?>">
        <h2 style="font-size:22px; margin-bottom:16px;">Alle Aanmelders</h2>
        
        <div class="stats">
          <div class="stat">
            <strong><?= $stats['totaal'] ?></strong>
            <span>Totaal</span>
          </div>
          <div class="stat">
            <strong><?= $stats['actief'] ?></strong>
            <span>Actief</span>
          </div>
          <div class="stat">
            <strong><?= $stats['uitgeschreven'] ?></strong>
            <span>Uitgeschreven</span>
          </div>
        </div>
        
        <?php if (empty($aanmelders)): ?>
          <p style="color: var(--muted); text-align:center; padding:40px;">Nog geen aanmeldingen.</p>
        <?php else: ?>
          <table>
            <thead>
              <tr>
                <th>Naam</th>
                <th>Email</th>
                <th>Aangemeld</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($aanmelders as $aanmelder): ?>
                <tr>
                  <td><?= htmlspecialchars($aanmelder['voornaam'] . ' ' . $aanmelder['achternaam']) ?></td>
                  <td><?= htmlspecialchars($aanmelder['email']) ?></td>
                  <td><?= date('d-m-Y H:i', strtotime($aanmelder['aanmeldmoment'])) ?></td>
                  <td>
                    <?php if ($aanmelder['uitgeschreven']): ?>
                      <span class="badge uitgeschreven">Uitgeschreven</span>
                      <?php if ($aanmelder['uitgeschreven_datum']): ?>
                        <br><small style="color: var(--muted); font-size:12px;"><?= date('d-m-Y', strtotime($aanmelder['uitgeschreven_datum'])) ?></small>
                      <?php endif; ?>
                    <?php else: ?>
                      <span class="badge actief">Actief</span>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>
    </div>
  </div>
  
  <script>
    function showTab(tabName) {
      // Hide all tabs
      document.querySelectorAll('.tab-content').forEach(tab => {
        tab.classList.remove('active');
      });
      document.querySelectorAll('.tab').forEach(btn => {
        btn.classList.remove('active');
      });
      
      // Show selected tab
      document.getElementById('tab-' + tabName).classList.add('active');
      event.target.classList.add('active');
      
      // Update URL
      const url = new URL(window.location);
      url.searchParams.set('tab', tabName);
      window.history.pushState({}, '', url);
    }
  </script>
</body>
</html>

