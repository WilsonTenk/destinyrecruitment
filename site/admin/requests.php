<?php
declare(strict_types=1);
require __DIR__ . '/../inc/admin-layout.php';
$user = require_login();

$status = isset($_GET['status']) && in_array($_GET['status'], ED_REQUEST_STATUSES, true) ? $_GET['status'] : '';
$counts = [];
foreach (db()->query('SELECT status, COUNT(*) AS n FROM requests GROUP BY status') as $r) {
    $counts[$r['status']] = (int)$r['n'];
}
$total = array_sum($counts);

if ($status) {
    $st = db()->prepare('SELECT * FROM requests WHERE status = ? ORDER BY created_at DESC LIMIT 300');
    $st->execute([$status]);
} else {
    $st = db()->query("SELECT * FROM requests ORDER BY CASE status WHEN 'new' THEN 0 ELSE 1 END, created_at DESC LIMIT 300");
}
$rows = $st->fetchAll();

admin_header('Requests', $user, 'requests');
?>
    <div class="head-row">
      <div>
        <h1>Requests</h1>
        <p class="muted">Every “Build your request” and call-back form from the website. WhatsApp still opens for the visitor; this is your record.</p>
      </div>
    </div>
    <nav class="filters" aria-label="Filter by status">
      <a href="requests.php"<?= $status === '' ? ' aria-current="page"' : '' ?>>All (<?= $total ?>)</a>
<?php foreach (ED_REQUEST_STATUSES as $s): ?>
      <a href="requests.php?status=<?= $s ?>"<?= $status === $s ? ' aria-current="page"' : '' ?>><?= ucfirst($s) ?> (<?= $counts[$s] ?? 0 ?>)</a>
<?php endforeach; ?>
    </nav>
    <div class="card table-wrap">
<?php if (!$rows): ?>
      <p class="muted">No requests yet. They appear here as soon as someone sends a form on the website.</p>
<?php else: ?>
      <table>
        <thead><tr><th>Received</th><th>Status</th><th>Name / phone</th><th>Services</th><th>Home &amp; schedule</th><th></th></tr></thead>
        <tbody>
<?php foreach ($rows as $r): ?>
          <tr>
            <td><?= h(substr($r['created_at'], 0, 16)) ?> <span class="hint">UTC</span></td>
            <td><span class="pill <?= h($r['status']) ?>"><?= h(ucfirst($r['status'])) ?></span><span class="hint"><?= $r['kind'] === 'build' ? 'Request form' : 'Call-back' ?></span></td>
            <td><?= h($r['name'] ?: '—') ?><span class="hint"><?= h($r['phone'] ?: 'No number (check WhatsApp)') ?></span></td>
            <td><?= h($r['services'] ?: '—') ?></td>
            <td><?= h(trim($r['home'] . ($r['schedule'] ? ' · ' . $r['schedule'] : ''), ' ·') ?: '—') ?></td>
            <td><a href="request.php?id=<?= (int)$r['id'] ?>">Open</a></td>
          </tr>
<?php endforeach; ?>
        </tbody>
      </table>
<?php endif; ?>
    </div>
<?php admin_footer();
