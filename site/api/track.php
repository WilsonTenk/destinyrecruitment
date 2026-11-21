<?php
/**
 * Counts clicks on the website's WhatsApp buttons (for the admin WhatsApp report).
 * Anonymous: stores which button, which page, the language and the time. No names,
 * no cookies, and the visitor's address only as a scrambled code (for "different visitors").
 */
declare(strict_types=1);
require __DIR__ . '/../inc/bootstrap.php';

header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

function done(int $code): void
{
    http_response_code($code);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    done(405);
}
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin !== '' && parse_url($origin, PHP_URL_HOST) !== parse_url('http://' . ($_SERVER['HTTP_HOST'] ?? ''), PHP_URL_HOST)) {
    done(403);
}

$data = json_decode((string)file_get_contents('php://input', false, null, 0, 2048), true);
if (!is_array($data)) {
    done(400);
}
$sources = ['fab', 'menu', 'header', 'footer', 'contact-card', 'page'];
$source = is_string($data['source'] ?? null) && in_array($data['source'], $sources, true) ? $data['source'] : null;
$page = is_string($data['page'] ?? null) ? strtolower($data['page']) : '';
if (!$source || !preg_match('/^[a-z0-9-]{0,90}(\.html)?$/', $page)) {
    done(400);
}
$lang = ($data['lang'] ?? '') === 'ar' ? 'ar' : 'en';

$ip = ed_ip_hash();
// Ignore floods (60 an hour from one visitor) and double-clicks on the same button.
$st = db()->prepare("SELECT COUNT(*) FROM wa_clicks WHERE ip_hash = ? AND created_at > datetime('now', '-1 hour')");
$st->execute([$ip]);
if ((int)$st->fetchColumn() >= 60) {
    done(204);
}
$st = db()->prepare("SELECT COUNT(*) FROM wa_clicks WHERE ip_hash = ? AND source = ? AND page = ? AND created_at > datetime('now', '-5 seconds')");
$st->execute([$ip, $source, $page]);
if ((int)$st->fetchColumn() > 0) {
    done(204);
}

db()->prepare('INSERT INTO wa_clicks(created_at, source, page, lang, ip_hash) VALUES (?, ?, ?, ?, ?)')
    ->execute([ed_now(), $source, $page, $lang, $ip]);
done(204);
