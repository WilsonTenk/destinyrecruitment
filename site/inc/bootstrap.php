<?php
/**
 * Edinam Destiny – shared setup for the admin, the API and the server-rendered blog.
 * Database: one SQLite file, kept outside the public website folder when possible.
 * Works on PHP 7.4+.
 */
declare(strict_types=1);

if (!defined('ED_BOOT')) {
    define('ED_BOOT', true);
}

define('ED_SITE_DIR', dirname(__DIR__));                 // .../public_html
define('ED_UPLOAD_DIR', ED_SITE_DIR . '/uploads');
define('ED_UPLOAD_URL', 'uploads');
define('ED_SERVICES', ['nanny', 'cook', 'cleaner', 'carer', 'hosp', 'maid']);
define('ED_STATUSES', ['ok', 'ahead', 'off']);
define('ED_REQUEST_STATUSES', ['new', 'contacted', 'quoted', 'booked', 'closed']);

/* ---------------- Data folder ---------------- */
function ed_data_dir(): string
{
    static $dir = null;
    if ($dir !== null) {
        return $dir;
    }
    // Preferred: next to public_html (not reachable from the web), e.g. /home/user/destiny-data
    $outside = dirname(ED_SITE_DIR) . '/destiny-data';
    $inside = ED_SITE_DIR . '/data';
    foreach ([$outside, $inside] as $candidate) {
        if (is_dir($candidate) || @mkdir($candidate, 0750, true)) {
            if (is_writable($candidate)) {
                $dir = $candidate;
                break;
            }
        }
    }
    if ($dir === null) {
        http_response_code(500);
        exit('Data folder is not writable. Create a writable "destiny-data" folder next to public_html.');
    }
    // If we had to use a folder inside the website, lock it down.
    if ($dir === $inside && !file_exists($inside . '/.htaccess')) {
        @file_put_contents($inside . '/.htaccess', "Require all denied\n");
    }
    return $dir;
}

/* ---------------- Database ---------------- */
function db(): PDO
{
    static $pdo = null;
    if ($pdo) {
        return $pdo;
    }
    $pdo = new PDO('sqlite:' . ed_data_dir() . '/destiny.sqlite', null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec('PRAGMA journal_mode = WAL; PRAGMA foreign_keys = ON; PRAGMA busy_timeout = 5000;');
    ed_migrate($pdo);
    return $pdo;
}

function ed_migrate(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY, username TEXT UNIQUE NOT NULL, password_hash TEXT NOT NULL,
            created_at TEXT NOT NULL, last_login TEXT
        );
        CREATE TABLE IF NOT EXISTS login_attempts (
            id INTEGER PRIMARY KEY, ip_hash TEXT NOT NULL, attempted_at INTEGER NOT NULL
        );
        CREATE TABLE IF NOT EXISTS requests (
            id INTEGER PRIMARY KEY, kind TEXT NOT NULL, name TEXT, phone TEXT, services TEXT,
            home TEXT, extras TEXT, care TEXT, schedule TEXT, area TEXT, start_date TEXT,
            notes TEXT, lang TEXT, status TEXT NOT NULL DEFAULT 'new', admin_note TEXT,
            ip_hash TEXT, created_at TEXT NOT NULL, updated_at TEXT NOT NULL
        );
        CREATE TABLE IF NOT EXISTS posts (
            id INTEGER PRIMARY KEY, slug TEXT UNIQUE NOT NULL,
            title_en TEXT NOT NULL, title_ar TEXT NOT NULL DEFAULT '',
            excerpt_en TEXT NOT NULL DEFAULT '', excerpt_ar TEXT NOT NULL DEFAULT '',
            body_en TEXT NOT NULL DEFAULT '', body_ar TEXT NOT NULL DEFAULT '',
            takeaways_en TEXT NOT NULL DEFAULT '', takeaways_ar TEXT NOT NULL DEFAULT '',
            category_en TEXT NOT NULL DEFAULT '', category_ar TEXT NOT NULL DEFAULT '',
            cta_en TEXT NOT NULL DEFAULT '', cta_ar TEXT NOT NULL DEFAULT '',
            image TEXT NOT NULL DEFAULT '', image_alt_en TEXT NOT NULL DEFAULT '', image_alt_ar TEXT NOT NULL DEFAULT '',
            published INTEGER NOT NULL DEFAULT 0, published_at TEXT, created_at TEXT NOT NULL, updated_at TEXT NOT NULL
        );
        CREATE TABLE IF NOT EXISTS settings (key TEXT PRIMARY KEY, value TEXT NOT NULL);
        CREATE INDEX IF NOT EXISTS idx_requests_status ON requests(status, created_at);
        CREATE INDEX IF NOT EXISTS idx_attempts ON login_attempts(ip_hash, attempted_at);
    ");
    $defaults = [
        'phone1' => '+966544827315', 'phone2' => '+966596917524', 'whatsapp' => '966544827315',
        'hours_en' => '', 'hours_ar' => '', 'address_en' => '', 'address_ar' => '', 'maps_url' => '',
        'notify_email' => '', 'notify_on' => '1', 'mail_from' => '',
    ];
    foreach (ED_SERVICES as $s) {
        $defaults['avail_' . $s] = in_array($s, ['carer', 'hosp'], true) ? 'ahead' : 'ok';
    }
    $ins = $pdo->prepare('INSERT OR IGNORE INTO settings(key, value) VALUES (?, ?)');
    foreach ($defaults as $k => $v) {
        $ins->execute([$k, $v]);
    }
    ed_seed_posts($pdo);
}

function ed_seed_posts(PDO $pdo): void
{
    if ((int)$pdo->query('SELECT COUNT(*) FROM posts')->fetchColumn() > 0) {
        return;
    }
    $file = __DIR__ . '/seed-posts.json';
    if (!is_file($file)) {
        return;
    }
    $posts = json_decode((string)file_get_contents($file), true) ?: [];
    $cols = ['slug', 'title_en', 'title_ar', 'excerpt_en', 'excerpt_ar', 'body_en', 'body_ar', 'takeaways_en', 'takeaways_ar',
             'category_en', 'category_ar', 'cta_en', 'cta_ar', 'image', 'image_alt_en', 'image_alt_ar', 'published_at'];
    $sql = 'INSERT INTO posts(' . implode(',', $cols) . ', published, created_at, updated_at) VALUES ('
         . implode(',', array_fill(0, count($cols), '?')) . ', 1, ?, ?)';
    $st = $pdo->prepare($sql);
    foreach ($posts as $p) {
        $vals = array_map(function ($c) use ($p) { return (string)($p[$c] ?? ''); }, $cols);
        $vals[] = ed_now();
        $vals[] = ed_now();
        $st->execute($vals);
    }
}

function setting(string $key, string $default = ''): string
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        foreach (db()->query('SELECT key, value FROM settings') as $row) {
            $cache[$row['key']] = $row['value'];
        }
    }
    return $cache[$key] ?? $default;
}

function save_setting(string $key, string $value): void
{
    db()->prepare('INSERT INTO settings(key, value) VALUES (?, ?) ON CONFLICT(key) DO UPDATE SET value = excluded.value')
        ->execute([$key, $value]);
}

/* ---------------- Helpers ---------------- */
function h(?string $s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function ed_now(): string
{
    return gmdate('Y-m-d H:i:s');
}

function ed_is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
}

/** Privacy-friendly client fingerprint: a salted hash, never the raw IP. */
function ed_ip_hash(): string
{
    $saltFile = ed_data_dir() . '/salt.txt';
    if (!is_file($saltFile)) {
        @file_put_contents($saltFile, bin2hex(random_bytes(32)));
    }
    $salt = (string)@file_get_contents($saltFile);
    return hash('sha256', $salt . '|' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
}

function clean_text($value, int $max = 500): string
{
    $s = is_string($value) ? $value : '';
    $s = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $s) ?? '';
    $s = trim($s);
    return mb_substr($s, 0, $max);
}

function slugify(string $text): string
{
    $s = strtolower(trim($text));
    $s = preg_replace('/[^a-z0-9]+/', '-', $s) ?? '';
    $s = trim($s, '-');
    return $s === '' ? 'post' : substr($s, 0, 70);
}

/* ---------------- Sessions, auth, CSRF ---------------- */
function ed_session_start(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    session_name('ed_admin');
    session_set_cookie_params([
        'lifetime' => 0, 'path' => '/', 'secure' => ed_is_https(), 'httponly' => true, 'samesite' => 'Strict',
    ]);
    ini_set('session.use_strict_mode', '1');
    session_start();
    $idle = 2 * 3600;
    if (isset($_SESSION['last_seen']) && time() - $_SESSION['last_seen'] > $idle) {
        $_SESSION = [];
        session_regenerate_id(true);
    }
    $_SESSION['last_seen'] = time();
}

function csrf_token(): string
{
    ed_session_start();
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . h(csrf_token()) . '">';
}

function csrf_check(): void
{
    ed_session_start();
    $sent = $_POST['csrf'] ?? '';
    if (!is_string($sent) || empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $sent)) {
        http_response_code(400);
        exit('Your session expired. Go back, refresh the page and try again.');
    }
}

function current_user(): ?array
{
    ed_session_start();
    if (empty($_SESSION['uid'])) {
        return null;
    }
    $st = db()->prepare('SELECT id, username FROM users WHERE id = ?');
    $st->execute([$_SESSION['uid']]);
    return $st->fetch() ?: null;
}

function require_login(): array
{
    $u = current_user();
    if (!$u) {
        header('Location: login.php');
        exit;
    }
    return $u;
}

function too_many_attempts(): bool
{
    $since = time() - 15 * 60;
    db()->prepare('DELETE FROM login_attempts WHERE attempted_at < ?')->execute([time() - 86400]);
    $st = db()->prepare('SELECT COUNT(*) FROM login_attempts WHERE ip_hash = ? AND attempted_at > ?');
    $st->execute([ed_ip_hash(), $since]);
    return (int)$st->fetchColumn() >= 5;
}

function record_failed_attempt(): void
{
    db()->prepare('INSERT INTO login_attempts(ip_hash, attempted_at) VALUES (?, ?)')->execute([ed_ip_hash(), time()]);
}

function clear_attempts(): void
{
    db()->prepare('DELETE FROM login_attempts WHERE ip_hash = ?')->execute([ed_ip_hash()]);
}

function admin_headers(): void
{
    header('X-Robots-Tag: noindex, nofollow');
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: same-origin');
    header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self'; script-src 'self'; form-action 'self'; frame-ancestors 'none'; base-uri 'self'; object-src 'none'");
    header('Cache-Control: no-store');
}

function flash(?string $msg = null): ?string
{
    ed_session_start();
    if ($msg !== null) {
        $_SESSION['flash'] = $msg;
        return null;
    }
    $m = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $m;
}

/* ---------------- Email ----------------
 * Uses the server's built-in mail (cPanel provides it). For local testing,
 * set the environment variable ED_MAIL_LOG=1 and emails are written to
 * destiny-data/mail-log.txt instead of being sent.
 */
function ed_site_host(): string
{
    $host = strtolower((string)($_SERVER['HTTP_HOST'] ?? 'localhost'));
    $host = preg_replace('/:\d+$/', '', $host) ?? '';
    return preg_match('/^[a-z0-9.-]{1,190}$/', $host) ? $host : 'localhost';
}

function ed_valid_email(string $email): bool
{
    return (bool)filter_var($email, FILTER_VALIDATE_EMAIL) && !preg_match('/[\r\n]/', $email);
}

function ed_mail(string $to, string $subject, string $body): bool
{
    if (!ed_valid_email($to)) {
        return false;
    }
    // Nothing a visitor typed ever goes into the email headers.
    $subject = trim(preg_replace('/[\r\n\t]+/', ' ', $subject) ?? '');
    $host = ed_site_host();
    $from = setting('mail_from');
    if (!ed_valid_email($from)) {
        $from = 'no-reply@' . preg_replace('/^www\./', '', $host);
    }
    $headers = [
        'From: Edinam Destiny website <' . $from . '>',
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'Content-Transfer-Encoding: 8bit',
        'X-Mailer: Edinam Destiny admin',
    ];
    $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    $body = str_replace(["\r\n", "\r"], "\n", $body);

    if (getenv('ED_MAIL_LOG')) {
        $entry = "==== " . gmdate('c') . "\nTo: $to\nSubject: $subject\n" . implode("\n", $headers) . "\n\n$body\n\n";
        return (bool)@file_put_contents(ed_data_dir() . '/mail-log.txt', $entry, FILE_APPEND);
    }
    return @mail($to, $encodedSubject, str_replace("\n", "\r\n", $body), implode("\r\n", $headers), '-f' . $from);
}

/** Emails the owner about a new request (if notifications are switched on). Never throws. */
function notify_new_request(int $id, array $row): void
{
    try {
        $to = setting('notify_email');
        if (setting('notify_on', '1') !== '1' || !ed_valid_email($to)) {
            return;
        }
        $scheme = ed_is_https() ? 'https' : 'http';
        $link = $scheme . '://' . (string)($_SERVER['HTTP_HOST'] ?? ed_site_host()) . '/admin/request.php?id=' . $id;
        if (!preg_match('#^https?://[A-Za-z0-9.:-]+/admin/request\.php\?id=\d+$#', $link)) {
            $link = '/admin/request.php?id=' . $id;
        }
        $what = $row['services'] !== '' ? $row['services'] : ($row['kind'] === 'callback' ? 'Call-back' : 'Request');
        $subject = 'New website request #' . $id . ': ' . (function_exists('mb_substr') ? mb_substr($what, 0, 80) : $what);
        $lines = [
            'You have a new request on the Edinam Destiny website.',
            '',
            'Form:       ' . ($row['kind'] === 'build' ? 'Build your request' : 'Call-back request'),
        ];
        $labels = ['name' => 'Name', 'phone' => 'Phone', 'services' => 'Services', 'home' => 'Home', 'extras' => 'Also',
                   'care' => 'Care for', 'schedule' => 'Schedule', 'area' => 'Area', 'start_date' => 'Start date', 'notes' => 'Notes'];
        foreach ($labels as $k => $label) {
            if (($row[$k] ?? '') !== '') {
                $v = $k === 'notes'
                    ? str_replace("\n", "\n            ", (string)$row[$k])
                    : trim(preg_replace('/\s+/u', ' ', (string)$row[$k]) ?? '');
                $lines[] = str_pad($label . ':', 12) . $v;
            }
        }
        $lines[] = 'Language:   ' . ($row['lang'] === 'ar' ? 'Arabic' : 'English');
        $lines[] = '';
        $lines[] = 'Open it in the admin: ' . $link;
        $lines[] = '';
        $lines[] = 'The visitor was also sent to WhatsApp with this request, so check your WhatsApp too.';
        $lines[] = 'To stop these emails, untick "Email me about new requests" in Admin > Settings.';
        ed_mail($to, $subject, implode("\n", $lines));
    } catch (Throwable $e) {
        // A failed email must never stop the request from being saved.
    }
}

/* ---------------- Simple text formatting for blog bodies ----------------
 * Paragraphs separated by a blank line, "## " headings, "- " bullet lists,
 * **bold** and [link text](https://...). Everything is escaped first, so
 * nothing typed in the admin can inject HTML or scripts.
 */
function render_body(string $text): string
{
    $text = str_replace("\r\n", "\n", trim($text));
    if ($text === '') {
        return '';
    }
    $blocks = preg_split('/\n\s*\n/', $text) ?: [];
    $out = [];
    $first = true;
    foreach ($blocks as $block) {
        $lines = array_values(array_filter(array_map('trim', explode("\n", $block)), 'strlen'));
        if (!$lines) {
            continue;
        }
        if (strpos($lines[0], '## ') === 0 && count($lines) === 1) {
            $out[] = '<h2>' . inline_fmt(substr($lines[0], 3)) . '</h2>';
            continue;
        }
        $allBullets = count(array_filter($lines, function ($l) { return strpos($l, '- ') === 0; })) === count($lines);
        if ($allBullets) {
            $items = array_map(function ($l) { return '<li>' . inline_fmt(substr($l, 2)) . '</li>'; }, $lines);
            $out[] = '<ul>' . implode('', $items) . '</ul>';
            continue;
        }
        $html = implode(' ', array_map('inline_fmt', $lines));
        $out[] = $first ? '<p class="lead-p">' . $html . '</p>' : '<p>' . $html . '</p>';
        $first = false;
    }
    return implode("\n", $out);
}

function inline_fmt(string $s): string
{
    $s = h($s);
    $s = preg_replace('/\*\*(.+?)\*\*/u', '<strong>$1</strong>', $s) ?? $s;
    $s = preg_replace_callback('/\[([^\]]+)\]\((https?:\/\/[^\s)]+)\)/u', function ($m) {
        return '<a href="' . $m[2] . '" target="_blank" rel="noopener">' . $m[1] . '</a>';
    }, $s) ?? $s;
    return $s;
}

function lines_list(string $text): array
{
    return array_values(array_filter(array_map('trim', explode("\n", str_replace("\r\n", "\n", $text))), 'strlen'));
}
