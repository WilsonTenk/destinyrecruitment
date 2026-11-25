<?php
/** WhatsApp report: every tap on a WhatsApp button and every form sent to WhatsApp, by period. */
declare(strict_types=1);
require __DIR__ . '/../inc/admin-layout.php';
$user = require_login();

$tz = new DateTimeZone('Asia/Riyadh');
$utc = new DateTimeZone('UTC');
$today = new DateTimeImmutable('today', $tz);

$ranges = ['today' => 'Today', 'yesterday' => 'Yesterday', 'week' => 'This week', '7d' => 'Last 7 days',
           'month' => 'This month', 'lastmonth' => 'Last month', '30d' => 'Last 30 days', 'custom' => 'Custom'];
$range = is_string($_GET['range'] ?? null) && isset($ranges[$_GET['range']]) ? $_GET['range'] : '7d';
$parseDate = function ($v) use ($tz): ?DateTimeImmutable {
    if (!is_string($v) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) {
        return null;
    }
    $d = DateTimeImmutable::createFromFormat('!Y-m-d', $v, $tz);
    return $d ?: null;
};
$tomorrow = $today->modify('+1 day');
switch ($range) {
    case 'today':     $from = $today; $to = $tomorrow; break;
    case 'yesterday': $from = $today->modify('-1 day'); $to = $today; break;
    case 'week':      $from = $today->modify('-' . (int)$today->format('w') . ' days'); $to = $tomorrow; break; // weeks start on Sunday
    case 'month':     $from = $today->modify('first day of this month'); $to = $tomorrow; break;
    case 'lastmonth': $from = $today->modify('first day of last month'); $to = $today->modify('first day of this month'); break;
    case '30d':       $from = $today->modify('-29 days'); $to = $tomorrow; break;
    case 'custom':
        $from = $parseDate($_GET['from'] ?? '') ?? $today->modify('-6 days');
        $last = $parseDate($_GET['to'] ?? '') ?? $today;
        if ($last < $from) {
            [$from, $last] = [$last, $from];
        }
        if ($from < $last->modify('-5 years')) {
            $from = $last->modify('-5 years');
        }
        $to = $last->modify('+1 day');
        break;
    default:          $from = $today->modify('-6 days'); $to = $tomorrow;
}
$days = (int)$from->diff($to)->days;
$by = is_string($_GET['by'] ?? null) && in_array($_GET['by'], ['day', 'week', 'month'], true)
    ? $_GET['by'] : ($days <= 31 ? 'day' : ($days <= 120 ? 'week' : 'month'));

$st = db()->prepare('SELECT w.*, r.name AS r_name, r.phone AS r_phone, r.status AS r_status
                     FROM wa_clicks w LEFT JOIN requests r ON r.id = w.request_id
                     WHERE w.created_at >= ? AND w.created_at < ? ORDER BY w.created_at DESC, w.id DESC');
$st->execute([$from->setTimezone($utc)->format('Y-m-d H:i:s'), $to->setTimezone($utc)->format('Y-m-d H:i:s')]);
$rows = $st->fetchAll();

$sourceNames = [
    'builder' => 'Request builder (Services page)', 'callback' => 'Call-back form (Contact page)',
    'fab' => 'Round WhatsApp button', 'menu' => 'Menu', 'header' => 'Top bar', 'footer' => 'Footer',
    'contact-card' => 'WhatsApp card (Contact page)', 'page' => 'Button on the page',
];
$pageName = function (string $p): string {
    $p = preg_replace('/\.html$/', '', $p) ?? $p; // older records were saved as services.html, newer as services
    $map = ['' => 'Home', 'index' => 'Home', 'services' => 'Services', 'about' => 'About', 'contact' => 'Contact',
            'blog' => 'Blog', 'privacy' => 'Privacy', '404' => 'Page not found'];
    if (isset($map[$p])) {
        return $map[$p];
    }
    if (preg_match('/^blog-(.+)$/', $p, $m)) {
        return 'Article: ' . ucfirst(str_replace('-', ' ', $m[1]));
    }
    return $p;
};
$local = function (string $utcTime) use ($tz, $utc): DateTimeImmutable {
    return (new DateTimeImmutable($utcTime, $utc))->setTimezone($tz);
};

/* ---------- CSV download ---------- */
if (($_GET['export'] ?? '') === 'csv') {
    $cell = function (string $v): string {
        // Stop spreadsheet formulas sneaking in through visitor text.
        return preg_match('/^[=+\-@\t\r]/', $v) ? "'" . $v : $v;
    };
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="whatsapp-' . $from->format('Y-m-d') . '-to-' . $to->modify('-1 day')->format('Y-m-d') . '.csv"');
    admin_headers();
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // so Excel shows Arabic correctly
    fputcsv($out, ['Date (Riyadh)', 'Time', 'Where', 'Page', 'Services', 'Name', 'Phone', 'Request #', 'Language']);
    foreach ($rows as $r) {
        $d = $local($r['created_at']);
        fputcsv($out, array_map($cell, [$d->format('Y-m-d'), $d->format('H:i'), $sourceNames[$r['source']] ?? $r['source'],
            $pageName((string)$r['page']), (string)$r['services'], (string)$r['r_name'], (string)$r['r_phone'],
            $r['request_id'] ? (string)$r['request_id'] : '', $r['lang'] === 'ar' ? 'Arabic' : 'English']));
    }
    fclose($out);
    exit;
}

/* ---------- Numbers ---------- */
$total = count($rows);
$visitors = count(array_unique(array_filter(array_column($rows, 'ip_hash'))));
$withDetails = count(array_filter($rows, function ($r) { return $r['request_id'] !== null; }));
$bySource = $byService = $byPage = [];
foreach ($rows as $r) {
    $bySource[$r['source']] = ($bySource[$r['source']] ?? 0) + 1;
    $byPage[$pageName((string)$r['page'])] = ($byPage[$pageName((string)$r['page'])] ?? 0) + 1;
    foreach (array_filter(array_map('trim', explode(',', (string)$r['services']))) as $s) {
        $byService[$s] = ($byService[$s] ?? 0) + 1;
    }
}
arsort($bySource);
arsort($byService);
arsort($byPage);

// Timeline buckets (day, week starting Sunday, or month).
$bucketStart = function (DateTimeImmutable $d) use ($by): DateTimeImmutable {
    if ($by === 'week') {
        return $d->setTime(0, 0)->modify('-' . (int)$d->format('w') . ' days');
    }
    if ($by === 'month') {
        return $d->setTime(0, 0)->modify('first day of this month');
    }
    return $d->setTime(0, 0);
};
$step = ['day' => '+1 day', 'week' => '+7 days', 'month' => '+1 month'][$by];
$buckets = [];
for ($d = $bucketStart($from); $d < $to && count($buckets) < 400; $d = $d->modify($step)) {
    $buckets[$d->format('Y-m-d')] = ['start' => $d, 'n' => 0];
}
foreach ($rows as $r) {
    $k = $bucketStart($local($r['created_at']))->format('Y-m-d');
    if (isset($buckets[$k])) {
        $buckets[$k]['n']++;
    }
}
$peak = $buckets ? max(array_column($buckets, 'n')) : 0;
$busiest = null;
foreach ($buckets as $b) {
    if ($b['n'] > 0 && (!$busiest || $b['n'] > $busiest['n'])) {
        $busiest = $b;
    }
}
$bucketLabel = function (DateTimeImmutable $d) use ($by): string {
    return $by === 'month' ? $d->format('F Y') : ($by === 'week' ? 'Week of ' . $d->format('j M') : $d->format('D j M'));
};

$q = function (array $extra) use ($range, $by, $from, $to): string {
    $base = ['range' => $range, 'by' => $by];
    if ($range === 'custom') {
        $base['from'] = $from->format('Y-m-d');
        $base['to'] = $to->modify('-1 day')->format('Y-m-d');
    }
    return '?' . http_build_query(array_merge($base, $extra));
};
$periodText = $from->format('j M Y') . ($days > 1 ? ' – ' . $to->modify('-1 day')->format('j M Y') : '');

admin_header('WhatsApp', $user, 'whatsapp');
?>
    <div class="head-row">
      <div>
        <h1>WhatsApp</h1>
        <p class="muted">Each time a visitor taps a WhatsApp button or sends a form to WhatsApp. Shows that WhatsApp was opened; it can’t see whether they pressed Send.</p>
      </div>
      <a class="btn btn-ghost" href="<?= h($q(['export' => 'csv'])) ?>">Download (Excel / CSV)</a>
    </div>

    <nav class="filters" aria-label="Period">
<?php foreach ($ranges as $key => $label): if ($key === 'custom') { continue; } ?>
      <a href="?<?= h(http_build_query(['range' => $key])) ?>"<?= $range === $key ? ' aria-current="page"' : '' ?>><?= h($label) ?></a>
<?php endforeach; ?>
    </nav>
    <form class="card range-form" method="get">
      <input type="hidden" name="range" value="custom">
      <label>From<input type="date" name="from" value="<?= h($from->format('Y-m-d')) ?>" max="<?= h($today->format('Y-m-d')) ?>"></label>
      <label>To<input type="date" name="to" value="<?= h($to->modify('-1 day')->format('Y-m-d')) ?>" max="<?= h($today->format('Y-m-d')) ?>"></label>
      <label>Show by
        <select name="by">
<?php foreach (['day' => 'Day', 'week' => 'Week', 'month' => 'Month'] as $k => $l): ?>
          <option value="<?= $k ?>"<?= $by === $k ? ' selected' : '' ?>><?= $l ?></option>
<?php endforeach; ?>
        </select>
      </label>
      <button class="btn" type="submit">Show</button>
    </form>

    <p class="period"><strong><?= h($ranges[$range]) ?></strong> · <?= h($periodText) ?> · Riyadh time</p>
    <div class="stats">
      <div class="stat"><span class="stat-n"><?= $total ?></span><span class="stat-l">WhatsApp taps</span></div>
      <div class="stat"><span class="stat-n"><?= $visitors ?></span><span class="stat-l">Different visitors</span></div>
      <div class="stat"><span class="stat-n"><?= $withDetails ?></span><span class="stat-l">Left name / phone (forms)</span></div>
      <div class="stat"><span class="stat-n stat-small"><?= $busiest ? h($bucketLabel($busiest['start'])) : '–' ?></span><span class="stat-l">Busiest <?= h($by) ?><?= $busiest ? ' (' . $busiest['n'] . ')' : '' ?></span></div>
    </div>

    <section class="card">
      <h2>By <?= h($by) ?></h2>
      <div class="table-wrap">
        <table class="bars">
          <tbody>
<?php foreach (array_reverse($buckets) as $b): ?>
            <tr><td class="bar-label"><?= h($bucketLabel($b['start'])) ?></td><td class="bar-cell"><progress max="<?= max(1, $peak) ?>" value="<?= $b['n'] ?>"><?= $b['n'] ?></progress></td><td class="bar-n"><?= $b['n'] ?></td></tr>
<?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </section>

    <div class="grid3">
<?php foreach (['Where they tapped' => [$bySource, $sourceNames], 'Services asked for' => [$byService, []], 'Pages' => [$byPage, []]] as $title => [$list, $names]): ?>
      <section class="card">
        <h2><?= h($title) ?></h2>
<?php if (!$list): ?>
        <p class="muted">Nothing in this period.</p>
<?php else: ?>
        <ul class="tally">
<?php foreach (array_slice($list, 0, 10, true) as $k => $n): ?>
          <li><span><?= h($names[$k] ?? (string)$k) ?></span><strong><?= $n ?></strong></li>
<?php endforeach; ?>
        </ul>
<?php endif; ?>
<?php if ($title === 'Services asked for'): ?>
        <p class="hint">From the request builder and call-back form.</p>
<?php endif; ?>
      </section>
<?php endforeach; ?>
    </div>

    <section class="card">
      <h2>All taps <span class="muted">(newest first<?= $total > 300 ? ', showing 300 of ' . $total . '. Download for all' : '' ?>)</span></h2>
<?php if (!$rows): ?>
      <p class="muted">No WhatsApp taps in this period yet.</p>
<?php else: ?>
      <div class="table-wrap">
        <table>
          <thead><tr><th>When</th><th>Where</th><th>Page</th><th>Services</th><th>Contact</th></tr></thead>
          <tbody>
<?php foreach (array_slice($rows, 0, 300) as $r): $d = $local($r['created_at']); ?>
            <tr>
              <td class="nowrap"><?= h($d->format('j M, g:i a')) ?></td>
              <td><?= h($sourceNames[$r['source']] ?? $r['source']) ?><?= $r['lang'] === 'ar' ? ' <span class="pill">AR</span>' : '' ?></td>
              <td><?= h($pageName((string)$r['page'])) ?></td>
              <td><?= h((string)$r['services']) ?></td>
              <td><?php if ($r['request_id'] && $r['r_status'] !== null): ?><a href="request.php?id=<?= (int)$r['request_id'] ?>"><?= h(trim($r['r_name'] . ' ' . $r['r_phone']) ?: 'Request #' . (int)$r['request_id']) ?></a> <span class="pill <?= h($r['r_status']) ?>"><?= h(ucfirst($r['r_status'])) ?></span><?php elseif ($r['request_id']): ?><span class="muted">Request deleted</span><?php else: ?><span class="muted">Anonymous</span><?php endif; ?></td>
            </tr>
<?php endforeach; ?>
          </tbody>
        </table>
      </div>
<?php endif; ?>
    </section>
<?php admin_footer();
