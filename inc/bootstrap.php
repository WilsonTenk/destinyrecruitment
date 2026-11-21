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

// Never show PHP error details (file paths, queries) to visitors. Errors go to
// destiny-data/php-errors.log instead.
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('expose_php', '0');

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
    ini_set('error_log', $dir . '/php-errors.log');
    return $dir;
}

/* ---------------- Database ---------------- */
function db(): PDO
{
    static $pdo = null;
    if ($pdo) {
        return $pdo;
    }
    $file = ed_data_dir() . '/destiny.sqlite';
    $pdo = new PDO('sqlite:' . $file, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    @chmod($file, 0640);
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
        CREATE TABLE IF NOT EXISTS wa_clicks (
            id INTEGER PRIMARY KEY, created_at TEXT NOT NULL, source TEXT NOT NULL, page TEXT NOT NULL DEFAULT '',
            services TEXT NOT NULL DEFAULT '', lang TEXT NOT NULL DEFAULT 'en', request_id INTEGER, ip_hash TEXT
        );
        CREATE INDEX IF NOT EXISTS idx_wa_time ON wa_clicks(created_at);
        CREATE TABLE IF NOT EXISTS auth_log (
            id INTEGER PRIMARY KEY, at TEXT NOT NULL, event TEXT NOT NULL, username TEXT, ip TEXT, agent TEXT
        );
    ");
    // Columns added after the first release (two-step login, sign-out everywhere).
    $have = array_column($pdo->query('PRAGMA table_info(users)')->fetchAll(PDO::FETCH_ASSOC), 'name');
    $add = [
        'totp_secret' => "TEXT NOT NULL DEFAULT ''", 'totp_on' => 'INTEGER NOT NULL DEFAULT 0',
        'totp_last' => 'INTEGER NOT NULL DEFAULT 0', 'recovery' => "TEXT NOT NULL DEFAULT ''",
        'session_ver' => 'INTEGER NOT NULL DEFAULT 1',
    ];
    foreach ($add as $col => $def) {
        if (!in_array($col, $have, true)) {
            $pdo->exec("ALTER TABLE users ADD COLUMN $col $def");
        }
    }
    $defaults = [
        'phone1' => '+966544827315', 'phone2' => '+966596917524', 'whatsapp' => '966544827315',
        'hours_en' => '', 'hours_ar' => '', 'address_en' => '', 'address_ar' => '', 'maps_url' => '',
        'notify_email' => '', 'notify_on' => '1', 'mail_from' => '', 'site_host' => '', 'lock_alert_at' => '0',
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
    // Keep session files in the private data folder (not the shared server temp folder).
    $sessDir = ed_data_dir() . '/sessions';
    if (is_dir($sessDir) || @mkdir($sessDir, 0700, true)) {
        session_save_path($sessDir);
        ini_set('session.gc_probability', '1');
        ini_set('session.gc_divisor', '50');
        ini_set('session.gc_maxlifetime', (string)(12 * 3600));
    }
    // The admin cookie is only sent to the admin folder, never to public pages.
    $path = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/') . '/';
    session_name('ed_admin');
    session_set_cookie_params([
        'lifetime' => 0, 'path' => $path, 'secure' => ed_is_https(), 'httponly' => true, 'samesite' => 'Strict',
    ]);
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_trans_sid', '0');
    session_start();
    $now = time();
    $idle = 2 * 3600;            // signed out after 2 hours of no activity
    $absolute = 12 * 3600;       // and always after 12 hours
    if ((isset($_SESSION['last_seen']) && $now - $_SESSION['last_seen'] > $idle)
        || (isset($_SESSION['started']) && $now - $_SESSION['started'] > $absolute)) {
        $_SESSION = [];
        session_regenerate_id(true);
    }
    $_SESSION['started'] = $_SESSION['started'] ?? $now;
    $_SESSION['last_seen'] = $now;
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
    $st = db()->prepare('SELECT id, username, session_ver, totp_on, recovery FROM users WHERE id = ?');
    $st->execute([$_SESSION['uid']]);
    $u = $st->fetch();
    // "Sign out everywhere", a password change or a two-step change ends all other sessions.
    if (!$u || (int)$u['session_ver'] !== (int)($_SESSION['ver'] ?? 0)) {
        $_SESSION = [];
        return null;
    }
    return $u;
}

/** Signs the user in on this browser (after password and, if on, the two-step code). */
function ed_finish_login(array $u, string $method = 'password'): void
{
    ed_session_start();
    clear_attempts();
    session_regenerate_id(true);
    $keepFlash = $_SESSION['flash'] ?? null;
    // Start clean: a new session ID and a new form token after logging in.
    $_SESSION = ['uid' => (int)$u['id'], 'ver' => (int)$u['session_ver'], 'started' => time(), 'last_seen' => time()];
    if ($keepFlash !== null) {
        $_SESSION['flash'] = $keepFlash;
    }
    db()->prepare('UPDATE users SET last_login = ? WHERE id = ?')->execute([ed_now(), $u['id']]);
    // Remember the site's real address for links in emails (the address the owner logs in on).
    save_setting('site_host', ed_request_host());
    auth_log('login' . ($method === 'password' ? '' : ' (' . $method . ')'), (string)$u['username']);
    ed_security_email('New login to your website admin',
        "Someone just logged in to the Edinam Destiny admin as \"" . $u['username'] . "\".\n\n"
        . 'Time:    ' . ed_riyadh_time() . "\n"
        . 'Device:  ' . ed_agent_summary() . "\n"
        . 'Address: ' . ed_client_ip() . "\n\n"
        . "If this was you, you can ignore this email.\n"
        . "If it was NOT you: log in, change your password and click \"Sign out all other devices\" in Admin > Settings,\n"
        . "then turn on two-step login.");
}

function bump_session_version(int $uid): void
{
    db()->prepare('UPDATE users SET session_ver = session_ver + 1 WHERE id = ?')->execute([$uid]);
    $st = db()->prepare('SELECT session_ver FROM users WHERE id = ?');
    $st->execute([$uid]);
    ed_session_start();
    $_SESSION['ver'] = (int)$st->fetchColumn();   // keep THIS browser signed in
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

/**
 * Brute-force protection:
 *  - 5 wrong tries from one internet address  → that address waits 15 minutes;
 *  - 30 wrong tries from anywhere in 15 minutes → all logins pause for 15 minutes
 *    (stops attacks spread over many addresses) and the owner gets an email.
 */
function too_many_attempts(): bool
{
    $since = time() - 15 * 60;
    db()->prepare('DELETE FROM login_attempts WHERE attempted_at < ?')->execute([time() - 86400]);
    $st = db()->prepare('SELECT COUNT(*) FROM login_attempts WHERE ip_hash = ? AND attempted_at > ?');
    $st->execute([ed_ip_hash(), $since]);
    if ((int)$st->fetchColumn() >= 5) {
        return true;
    }
    $st = db()->prepare('SELECT COUNT(*) FROM login_attempts WHERE attempted_at > ?');
    $st->execute([$since]);
    if ((int)$st->fetchColumn() >= 30) {
        if (time() - (int)setting('lock_alert_at', '0') > 3600) {
            save_setting('lock_alert_at', (string)time());
            auth_log('logins paused (many wrong passwords)', '');
            ed_security_email('Warning: many wrong logins on your website admin',
                "More than 30 wrong passwords were tried on the Edinam Destiny admin in the last 15 minutes,\n"
                . "so logins are paused for 15 minutes. Your account is safe.\n\n"
                . "Nothing else is needed if two-step login is on. If it is not on yet, please turn it on in Admin > Settings.\n"
                . "Time: " . ed_riyadh_time());
        }
        return true;
    }
    return false;
}

function record_failed_attempt(string $username = '', string $what = 'wrong password'): void
{
    db()->prepare('INSERT INTO login_attempts(ip_hash, attempted_at) VALUES (?, ?)')->execute([ed_ip_hash(), time()]);
    auth_log('failed: ' . $what, $username);
}

/* ---------------- Security log and alerts ---------------- */
function ed_client_ip(): string
{
    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
    return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : 'unknown';
}

/** Short, safe description of the browser, e.g. "Chrome on Windows". */
function ed_agent_summary(): string
{
    $ua = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');
    $browser = 'Unknown browser';
    foreach (['Edg/' => 'Edge', 'OPR/' => 'Opera', 'SamsungBrowser' => 'Samsung Internet', 'Firefox/' => 'Firefox',
              'CriOS' => 'Chrome', 'Chrome/' => 'Chrome', 'Safari/' => 'Safari', 'curl' => 'curl (script)'] as $needle => $name) {
        if (stripos($ua, $needle) !== false) { $browser = $name; break; }
    }
    $os = '';
    foreach (['iPhone' => 'iPhone', 'iPad' => 'iPad', 'Android' => 'Android', 'Windows' => 'Windows',
              'Mac OS' => 'Mac', 'Linux' => 'Linux'] as $needle => $name) {
        if (stripos($ua, $needle) !== false) { $os = $name; break; }
    }
    return $os !== '' ? "$browser on $os" : $browser;
}

function ed_riyadh_time(?string $utc = null): string
{
    try {
        $d = new DateTime($utc ?? 'now', new DateTimeZone('UTC'));
        $d->setTimezone(new DateTimeZone('Asia/Riyadh'));
        return $d->format('j M Y, g:i a') . ' (Riyadh)';
    } catch (Exception $e) {
        return (string)$utc;
    }
}

function auth_log(string $event, string $username): void
{
    try {
        db()->prepare('INSERT INTO auth_log(at, event, username, ip, agent) VALUES (?, ?, ?, ?, ?)')
            ->execute([ed_now(), mb_substr($event, 0, 80), mb_substr($username, 0, 40), ed_client_ip(), ed_agent_summary()]);
        db()->exec("DELETE FROM auth_log WHERE at < datetime('now', '-90 days')");
    } catch (Throwable $e) {
        // logging must never break a login
    }
}

/** Security emails go to the same address as request alerts (even if request alerts are switched off). */
function ed_security_email(string $subject, string $body): void
{
    try {
        $to = setting('notify_email');
        if (ed_valid_email($to)) {
            ed_mail($to, $subject, $body . "\n\n— Edinam Destiny website security");
        }
    } catch (Throwable $e) {
    }
}

/** Returns a message if the password is too weak, or null if it is fine. */
function password_problem(string $pass, string $username = ''): ?string
{
    if (strlen($pass) < 12) {
        return 'The password must be at least 12 characters. Three or four random words joined together work well.';
    }
    $lower = strtolower($pass);
    $core = preg_replace('/[^a-z]/', '', $lower) ?? '';
    $common = ['password', 'passwort', 'qwerty', 'qwertyuiop', 'asdfgh', 'letmein', 'welcome', 'admin', 'administrator',
               'iloveyou', 'abcdef', 'abcdefgh', 'monkey', 'dragon', 'football', 'sunshine', 'princess', 'login',
               'destiny', 'edinam', 'recruitment', 'riyadh', 'saudi', 'nanny', 'housemaid', 'changeme'];
    if (preg_match('/^(.)\1+$/', $pass) || preg_match('/^(0123456789|1234567890|123456789012|012345678901)/', $pass)) {
        return 'That password is too easy to guess. Please choose another.';
    }
    if (in_array($core, $common, true) || ($username !== '' && stripos($pass, $username) !== false)) {
        return 'That password is too easy to guess (it is a common word or contains your username). Please choose another.';
    }
    return null;
}

/* ---------------- Two-step login (authenticator app, RFC 6238) ---------------- */
function b32_encode(string $bin): string
{
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $bits = '';
    foreach (str_split($bin) as $c) {
        $bits .= str_pad(decbin(ord($c)), 8, '0', STR_PAD_LEFT);
    }
    $out = '';
    foreach (str_split($bits, 5) as $chunk) {
        $out .= $alphabet[bindec(str_pad($chunk, 5, '0'))];
    }
    return $out;
}

function b32_decode(string $s): string
{
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $s = strtoupper(preg_replace('/[^A-Za-z2-7]/', '', $s) ?? '');
    $bits = '';
    foreach (str_split($s) as $c) {
        $bits .= str_pad(decbin((int)strpos($alphabet, $c)), 5, '0', STR_PAD_LEFT);
    }
    $out = '';
    foreach (str_split($bits, 8) as $byte) {
        if (strlen($byte) === 8) {
            $out .= chr(bindec($byte));
        }
    }
    return $out;
}

function totp_new_secret(): string
{
    return b32_encode(random_bytes(20));
}

function totp_code(string $secret, int $step): string
{
    $hash = hash_hmac('sha1', pack('N2', 0, $step), b32_decode($secret), true);
    $o = ord($hash[19]) & 0x0f;
    $n = ((ord($hash[$o]) & 0x7f) << 24) | (ord($hash[$o + 1]) << 16) | (ord($hash[$o + 2]) << 8) | ord($hash[$o + 3]);
    return str_pad((string)($n % 1000000), 6, '0', STR_PAD_LEFT);
}

/** Returns the matching time step (allowing ±30 s clock drift), or 0. Each code works only once. */
function totp_match(string $secret, string $code, int $lastUsed = 0): int
{
    $code = preg_replace('/\D/', '', $code) ?? '';
    if (strlen($code) !== 6 || $secret === '') {
        return 0;
    }
    $now = intdiv(time(), 30);
    foreach ([0, -1, 1] as $d) {
        $step = $now + $d;
        if ($step > $lastUsed && hash_equals(totp_code($secret, $step), $code)) {
            return $step;
        }
    }
    return 0;
}

/** Checks a code from the app for a saved user and marks it used. */
function totp_check(array $u, string $code): bool
{
    $step = totp_match((string)$u['totp_secret'], $code, (int)$u['totp_last']);
    if ($step === 0) {
        return false;
    }
    db()->prepare('UPDATE users SET totp_last = ? WHERE id = ?')->execute([$step, $u['id']]);
    return true;
}

/** Makes 8 one-time recovery codes. Returns [codes to show once, value to store]. */
function recovery_new(): array
{
    $plain = [];
    $hashes = [];
    for ($i = 0; $i < 8; $i++) {
        $c = strtoupper(bin2hex(random_bytes(3)) . '-' . bin2hex(random_bytes(3)));
        $plain[] = $c;
        $hashes[] = password_hash($c, PASSWORD_DEFAULT);
    }
    return [$plain, json_encode($hashes)];
}

function recovery_left(array $u): int
{
    return count(json_decode((string)($u['recovery'] ?? ''), true) ?: []);
}

/** Uses up one recovery code if it matches. */
function recovery_use(array $u, string $code): bool
{
    $code = strtoupper(trim($code));
    if (!preg_match('/^[0-9A-F]{6}-[0-9A-F]{6}$/', $code)) {
        return false;
    }
    $hashes = json_decode((string)$u['recovery'], true) ?: [];
    foreach ($hashes as $i => $hsh) {
        if (password_verify($code, (string)$hsh)) {
            unset($hashes[$i]);
            db()->prepare('UPDATE users SET recovery = ? WHERE id = ?')->execute([json_encode(array_values($hashes)), $u['id']]);
            return true;
        }
    }
    return false;
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
/** The address this request was made to, checked so a forged "Host" header can't inject anything. */
function ed_request_host(): string
{
    $host = strtolower((string)($_SERVER['HTTP_HOST'] ?? 'localhost'));
    return preg_match('/^[a-z0-9.-]{1,190}(:\d{1,5})?$/', $host) ? $host : 'localhost';
}

function ed_site_host(): string
{
    return preg_replace('/:\d+$/', '', ed_request_host()) ?? 'localhost';
}

/** Trusted site address for links in emails: the one the owner logs in on (not what a visitor's request claims). */
function ed_trusted_base(): string
{
    $host = setting('site_host');
    if (!preg_match('/^[a-z0-9.-]{1,190}(:\d{1,5})?$/', $host)) {
        return '';
    }
    $local = preg_match('/^(localhost|127\.0\.0\.1)(:\d+)?$/', $host);
    return ($local ? 'http' : 'https') . '://' . $host;
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
        // The link uses the saved site address, so a forged request can't point it to a fake site.
        $base = ed_trusted_base();
        $link = $base !== '' ? $base . '/admin/request.php?id=' . $id : 'Admin > Requests, request #' . $id;
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
