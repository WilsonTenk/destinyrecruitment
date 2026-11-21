<?php
/**
 * Saves a request from the website forms ("Build your request" and the
 * call-back form). WhatsApp still opens in the visitor's browser; this just
 * keeps a copy in the admin inbox. Accepts JSON (sent with navigator.sendBeacon).
 */
declare(strict_types=1);
require __DIR__ . '/../inc/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

function reply(int $code, array $body): void
{
    http_response_code($code);
    echo json_encode($body);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    reply(405, ['ok' => false, 'error' => 'POST only']);
}

// Only accept submissions from this site's own pages.
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$host = $_SERVER['HTTP_HOST'] ?? '';
if ($origin !== '' && parse_url($origin, PHP_URL_HOST) !== parse_url('http://' . $host, PHP_URL_HOST)) {
    reply(403, ['ok' => false, 'error' => 'Forbidden']);
}

$raw = file_get_contents('php://input', false, null, 0, 16384);
$data = json_decode((string)$raw, true);
if (!is_array($data)) {
    reply(400, ['ok' => false, 'error' => 'Bad request']);
}

// Spam trap: real visitors never fill the hidden "website" field.
if (!empty($data['website'])) {
    reply(200, ['ok' => true]);
}

$kind = in_array($data['kind'] ?? '', ['build', 'callback'], true) ? $data['kind'] : null;
if (!$kind) {
    reply(400, ['ok' => false, 'error' => 'Unknown form']);
}

// Rate limit: at most 20 requests per hour from one visitor.
$ipHash = ed_ip_hash();
$st = db()->prepare("SELECT COUNT(*) FROM requests WHERE ip_hash = ? AND created_at > datetime('now', '-1 hour')");
$st->execute([$ipHash]);
if ((int)$st->fetchColumn() >= 20) {
    reply(429, ['ok' => false, 'error' => 'Too many requests']);
}

$list = function ($v): string {
    if (!is_array($v)) {
        return '';
    }
    $items = array_slice(array_filter(array_map(function ($x) { return clean_text($x, 80); }, $v), 'strlen'), 0, 12);
    return implode(', ', $items);
};

$row = [
    'kind' => $kind,
    'name' => clean_text($data['name'] ?? '', 120),
    'phone' => clean_text($data['phone'] ?? '', 40),
    'services' => $list($data['services'] ?? []),
    'home' => $list($data['home'] ?? []),
    'extras' => $list($data['extras'] ?? []),
    'care' => $list($data['care'] ?? []),
    'schedule' => $list($data['schedule'] ?? []),
    'area' => clean_text($data['area'] ?? '', 120),
    'start_date' => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($data['start'] ?? '')) ? (string)$data['start'] : '',
    'notes' => clean_text($data['notes'] ?? '', 2000),
    'lang' => ($data['lang'] ?? '') === 'ar' ? 'ar' : 'en',
];
if ($row['services'] === '' && $row['phone'] === '' && $row['notes'] === '') {
    reply(400, ['ok' => false, 'error' => 'Empty request']);
}

$now = ed_now();
$cols = array_keys($row);
$sql = 'INSERT INTO requests(' . implode(',', $cols) . ', status, ip_hash, created_at, updated_at) VALUES ('
     . implode(',', array_fill(0, count($cols), '?')) . ", 'new', ?, ?, ?)";
db()->prepare($sql)->execute(array_merge(array_values($row), [$ipHash, $now, $now]));

notify_new_request((int)db()->lastInsertId(), $row);

reply(200, ['ok' => true]);
