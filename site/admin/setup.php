<?php
/**
 * First-time setup: create the admin account.
 * Only works while no account exists. To prove you own the server, it asks for
 * a one-time key saved in the private data folder (open it in cPanel File Manager).
 */
declare(strict_types=1);
require __DIR__ . '/../inc/admin-layout.php';

if ((int)db()->query('SELECT COUNT(*) FROM users')->fetchColumn() > 0) {
    header('Location: login.php');
    exit;
}

$keyFile = ed_data_dir() . '/setup-key.txt';
if (!is_file($keyFile)) {
    file_put_contents($keyFile, bin2hex(random_bytes(12)));
}
$expected = trim((string)file_get_contents($keyFile));
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $key = trim((string)($_POST['key'] ?? ''));
    $user = clean_text($_POST['username'] ?? '', 40);
    $pass = (string)($_POST['password'] ?? '');
    $pass2 = (string)($_POST['password2'] ?? '');
    if (too_many_attempts()) {
        $error = 'Too many failed attempts. Please wait 15 minutes and try again.';
    } elseif (!hash_equals($expected, $key)) {
        record_failed_attempt('', 'wrong setup key');
        $error = 'The setup key is not correct.';
    } elseif (!preg_match('/^[A-Za-z0-9_.-]{3,40}$/', $user)) {
        $error = 'Username: 3–40 letters, numbers, dots, dashes or underscores.';
    } elseif (($problem = password_problem($pass, $user)) !== null) {
        $error = $problem;
    } elseif ($pass !== $pass2) {
        $error = 'The two passwords do not match.';
    } else {
        db()->prepare('INSERT INTO users(username, password_hash, created_at) VALUES (?, ?, ?)')
            ->execute([$user, password_hash($pass, PASSWORD_DEFAULT), ed_now()]);
        @unlink($keyFile);
        $st = db()->prepare('SELECT * FROM users WHERE id = ?');
        $st->execute([(int)db()->lastInsertId()]);
        ed_finish_login($st->fetch(), 'first setup');
        flash('Your admin account is ready. Next: add your email and turn on two-step login below.');
        header('Location: settings.php');
        exit;
    }
}

$shownPath = str_replace(dirname(ED_SITE_DIR, 2), '…', $keyFile);
admin_header('Set up');
?>
    <div class="narrow card">
      <h1>Set up the admin</h1>
      <p class="muted">Create the account you’ll use to manage the website.</p>
      <p class="help">For security, enter the one-time <strong>setup key</strong>. Open this file in cPanel → File Manager:<br><code><?= h($shownPath) ?></code></p>
<?php if ($error): ?><p class="error"><?= h($error) ?></p><?php endif; ?>
      <form method="post" autocomplete="off">
        <?= csrf_field() ?>
        <label>Setup key<input type="text" name="key" required></label>
        <label>Username<input type="text" name="username" required autocomplete="username"></label>
        <label>Password <span class="hint">At least 12 characters. A short sentence works well.</span><input type="password" name="password" required minlength="12" autocomplete="new-password"></label>
        <label>Repeat password<input type="password" name="password2" required minlength="12" autocomplete="new-password"></label>
        <button class="btn btn-orange" type="submit">Create account</button>
      </form>
    </div>
<?php admin_footer();
