<?php
declare(strict_types=1);
require __DIR__ . '/../inc/admin-layout.php';

if ((int)db()->query('SELECT COUNT(*) FROM users')->fetchColumn() === 0) {
    header('Location: setup.php');
    exit;
}
if (current_user()) {
    header('Location: requests.php');
    exit;
}

/*
 * Step 1: username + password.
 * Step 2 (when two-step login is on): the 6-digit code from the authenticator app,
 *         or one of the one-time recovery codes. The half-finished login expires after 5 minutes.
 */
$pending = null;
if (!empty($_SESSION['pending_uid']) && time() - (int)($_SESSION['pending_at'] ?? 0) < 300) {
    $st = db()->prepare('SELECT * FROM users WHERE id = ?');
    $st->execute([(int)$_SESSION['pending_uid']]);
    $pending = $st->fetch() ?: null;
}
if (!$pending) {
    unset($_SESSION['pending_uid'], $_SESSION['pending_at']);
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (isset($_POST['cancel'])) {
        unset($_SESSION['pending_uid'], $_SESSION['pending_at']);
        header('Location: login.php');
        exit;
    }
    if (too_many_attempts()) {
        $error = 'Too many failed attempts. Please wait 15 minutes and try again.';
    } elseif ($pending) {
        $code = (string)($_POST['code'] ?? '');
        if (totp_check($pending, $code)) {
            ed_finish_login($pending, 'two-step');
            header('Location: requests.php');
            exit;
        }
        if (recovery_use($pending, $code)) {
            $st = db()->prepare('SELECT recovery FROM users WHERE id = ?');
            $st->execute([$pending['id']]);
            $left = recovery_left(['recovery' => (string)$st->fetchColumn()]);
            ed_finish_login($pending, 'recovery code');
            flash("You used a recovery code. $left left. If your phone is lost, turn two-step login off and on again in Settings to link a new phone.");
            header('Location: settings.php');
            exit;
        }
        record_failed_attempt((string)$pending['username'], 'wrong two-step code');
        $error = 'That code is not right. Use the newest 6-digit code in your authenticator app.';
    } else {
        $username = clean_text($_POST['username'] ?? '', 40);
        $password = (string)($_POST['password'] ?? '');
        $st = db()->prepare('SELECT * FROM users WHERE username = ?');
        $st->execute([$username]);
        $u = $st->fetch();
        $ok = $u && password_verify($password, $u['password_hash']);
        if (!$u) {
            // Spend the same time as a real check, so usernames can't be guessed by timing.
            password_verify('timing-equaliser', password_hash('timing-equaliser', PASSWORD_DEFAULT));
        }
        if ($ok) {
            if (password_needs_rehash($u['password_hash'], PASSWORD_DEFAULT)) {
                db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([password_hash($password, PASSWORD_DEFAULT), $u['id']]);
            }
            // Emergency switch if the phone AND recovery codes are lost: create the file
            // "disable-two-step.txt" in the private destiny-data folder with cPanel File Manager.
            $reset = ed_data_dir() . '/disable-two-step.txt';
            if ((int)$u['totp_on'] === 1 && is_file($reset)) {
                @unlink($reset);
                db()->prepare("UPDATE users SET totp_on = 0, totp_secret = '', recovery = '' WHERE id = ?")->execute([$u['id']]);
                auth_log('two-step login switched off with the server file', (string)$u['username']);
                ed_security_email('Two-step login was switched off', "Two-step login for the website admin was switched off using the file in the server's private folder.\nTime: " . ed_riyadh_time() . "\n\nIf this was not you, change your cPanel password straight away.");
                $u['totp_on'] = 0;
                flash('Two-step login was switched off with the server file. Please turn it on again below to link your phone.');
            }
            if ((int)$u['totp_on'] === 1) {
                session_regenerate_id(true);
                $_SESSION['pending_uid'] = (int)$u['id'];
                $_SESSION['pending_at'] = time();
                header('Location: login.php');
                exit;
            }
            ed_finish_login($u);
            header('Location: ' . (!empty($_SESSION['flash']) ? 'settings.php' : 'requests.php'));
            exit;
        }
        record_failed_attempt($username);
        $error = 'Wrong username or password.';
    }
}

admin_header('Log in');
?>
    <div class="narrow card">
<?php if ($pending): ?>
      <h1>Two-step login</h1>
      <p class="muted">Open your authenticator app and type the 6-digit code for <strong>Edinam Destiny</strong>.</p>
<?php if ($error): ?><p class="error"><?= h($error) ?></p><?php endif; ?>
      <form method="post" autocomplete="off">
        <?= csrf_field() ?>
        <label>Code<input type="text" name="code" required inputmode="numeric" autocomplete="one-time-code" maxlength="14" autofocus class="code-input"></label>
        <p class="hint">Lost your phone? Type one of your recovery codes instead (like <code>A1B2C3-D4E5F6</code>).</p>
        <div class="actions">
          <button class="btn btn-orange" type="submit">Continue</button>
          <button class="btn btn-ghost" type="submit" name="cancel" value="1" formnovalidate>Cancel</button>
        </div>
      </form>
<?php else: ?>
      <h1>Admin login</h1>
      <p class="muted">Edinam Destiny Recruitment</p>
<?php if ($error): ?><p class="error"><?= h($error) ?></p><?php endif; ?>
      <form method="post">
        <?= csrf_field() ?>
        <label>Username<input type="text" name="username" required autocomplete="username"></label>
        <label>Password<input type="password" name="password" required autocomplete="current-password"></label>
        <button class="btn btn-orange" type="submit">Log in</button>
      </form>
<?php endif; ?>
    </div>
<?php admin_footer();
