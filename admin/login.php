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

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (too_many_attempts()) {
        $error = 'Too many failed attempts. Please wait 15 minutes and try again.';
    } else {
        $st = db()->prepare('SELECT id, password_hash FROM users WHERE username = ?');
        $st->execute([clean_text($_POST['username'] ?? '', 40)]);
        $u = $st->fetch();
        $ok = $u && password_verify((string)($_POST['password'] ?? ''), $u['password_hash']);
        if (!$u) {
            // Spend the same time as a real check, so usernames can't be guessed by timing.
            password_verify('timing-equaliser', password_hash('timing-equaliser', PASSWORD_DEFAULT));
        }
        if ($ok) {
            clear_attempts();
            session_regenerate_id(true);
            $_SESSION['uid'] = (int)$u['id'];
            if (password_needs_rehash($u['password_hash'], PASSWORD_DEFAULT)) {
                db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([password_hash((string)$_POST['password'], PASSWORD_DEFAULT), $u['id']]);
            }
            db()->prepare('UPDATE users SET last_login = ? WHERE id = ?')->execute([ed_now(), $u['id']]);
            header('Location: requests.php');
            exit;
        }
        record_failed_attempt();
        $error = 'Wrong username or password.';
    }
}

admin_header('Log in');
?>
    <div class="narrow card">
      <h1>Admin login</h1>
      <p class="muted">Edinam Destiny Recruitment</p>
<?php if ($error): ?><p class="error"><?= h($error) ?></p><?php endif; ?>
      <form method="post">
        <?= csrf_field() ?>
        <label>Username<input type="text" name="username" required autocomplete="username"></label>
        <label>Password<input type="password" name="password" required autocomplete="current-password"></label>
        <button class="btn btn-orange" type="submit">Log in</button>
      </form>
    </div>
<?php admin_footer();
