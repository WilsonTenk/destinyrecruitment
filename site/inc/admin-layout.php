<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';

function admin_header(string $title, ?array $user = null, string $active = ''): void
{
    // Start the session and create the form token BEFORE any HTML is sent,
    // otherwise the browser never receives the session cookie.
    ed_session_start();
    csrf_token();
    admin_headers();
    header('Content-Type: text/html; charset=utf-8');
    $nav = [
        'requests' => ['requests.php', 'Requests'],
        'whatsapp' => ['whatsapp.php', 'WhatsApp'],
        'posts' => ['posts.php', 'Blog'],
        'services' => ['services.php', 'Availability'],
        'settings' => ['settings.php', 'Settings'],
    ];
    $newCount = 0;
    if ($user) {
        $newCount = (int)db()->query("SELECT COUNT(*) FROM requests WHERE status = 'new'")->fetchColumn();
    }
    ?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <title><?= h($title) ?> – Edinam Destiny admin</title>
  <link rel="icon" href="../assets/img/favicon.svg" type="image/svg+xml">
  <link rel="stylesheet" href="assets/admin.css?v=3">
</head>
<body>
<?php if ($user): ?>
  <header class="topbar">
    <a class="brand" href="requests.php"><img src="../assets/img/logo-mark.svg" alt="" width="30" height="34"><span>Edinam Destiny <small>admin</small></span></a>
    <nav aria-label="Admin menu">
<?php foreach ($nav as $key => [$href, $label]): ?>
      <a href="<?= $href ?>"<?= $active === $key ? ' aria-current="page"' : '' ?>><?= h($label) ?><?= $key === 'requests' && $newCount ? ' <span class="badge">' . $newCount . '</span>' : '' ?></a>
<?php endforeach; ?>
    </nav>
    <div class="topbar-end">
      <a href="../index.html" target="_blank" rel="noopener">View site</a>
      <form method="post" action="logout.php"><?= csrf_field() ?><button type="submit" class="link-btn">Log out</button></form>
    </div>
  </header>
<?php endif; ?>
  <main class="wrap">
<?php $f = flash(); if ($f): ?>
    <p class="flash" role="status"><?= h($f) ?></p>
<?php endif;
}

function admin_footer(): void
{
    ?>
  </main>
</body>
</html>
<?php
}
