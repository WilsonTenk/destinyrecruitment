<?php
declare(strict_types=1);
require __DIR__ . '/../inc/admin-layout.php';
$user = require_login();

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (($_POST['form'] ?? '') === 'contact') {
        $phone = function (string $v): string { return preg_replace('/[^\d+]/', '', $v) ?? ''; };
        $p1 = $phone(clean_text($_POST['phone1'] ?? '', 30));
        $p2 = $phone(clean_text($_POST['phone2'] ?? '', 30));
        $wa = preg_replace('/\D/', '', clean_text($_POST['whatsapp'] ?? '', 30)) ?? '';
        $maps = clean_text($_POST['maps_url'] ?? '', 500);
        if ($p1 !== '' && !preg_match('/^\+\d{8,15}$/', $p1)) {
            $errors[] = 'Main phone: use the international format, for example +966544827315.';
        }
        if ($p2 !== '' && !preg_match('/^\+\d{8,15}$/', $p2)) {
            $errors[] = 'Second phone: use the international format, for example +966596917524.';
        }
        if (!preg_match('/^\d{8,15}$/', $wa)) {
            $errors[] = 'WhatsApp number: digits only with the country code, for example 966544827315.';
        }
        if ($maps !== '' && !preg_match('#^https://(www\.)?(google\.[a-z.]+/maps|maps\.google\.[a-z.]+|maps\.app\.goo\.gl|goo\.gl/maps)#i', $maps)) {
            $errors[] = 'Google Maps link must be a Google Maps address starting with https://';
        }
        if (!$errors) {
            save_setting('phone1', $p1);
            save_setting('phone2', $p2);
            save_setting('whatsapp', $wa);
            save_setting('maps_url', $maps);
            foreach (['hours_en', 'hours_ar', 'address_en', 'address_ar'] as $k) {
                save_setting($k, clean_text($_POST[$k] ?? '', 200));
            }
            flash('Contact details saved. The website shows them straight away.');
            header('Location: settings.php');
            exit;
        }
    } elseif (($_POST['form'] ?? '') === 'notify') {
        $email = strtolower(clean_text($_POST['notify_email'] ?? '', 190));
        $from = strtolower(clean_text($_POST['mail_from'] ?? '', 190));
        if ($email !== '' && !ed_valid_email($email)) {
            $errors[] = 'Please enter a valid email address to receive alerts.';
        }
        if ($from !== '' && !ed_valid_email($from)) {
            $errors[] = 'The "send from" address is not a valid email address.';
        }
        if (!$errors) {
            save_setting('notify_email', $email);
            save_setting('mail_from', $from);
            save_setting('notify_on', isset($_POST['notify_on']) ? '1' : '0');
            if (isset($_POST['test'])) {
                if ($email === '') {
                    flash('Saved. Add an email address first to send a test.');
                } else {
                    $ok = ed_mail($email, 'Test email from your Edinam Destiny website',
                        "This is a test. If you can read this, new request alerts will reach this inbox.\n\nIf it landed in spam, mark it as \"Not spam\" so future alerts arrive normally.");
                    flash($ok ? 'Saved. Test email sent to ' . $email . ' (check spam too).' : 'Saved, but the server could not send the test email. See the README for email setup.');
                }
            } else {
                flash('Email notification settings saved.');
            }
            header('Location: settings.php');
            exit;
        }
    } elseif (in_array($_POST['form'] ?? '', ['password', '2fa_off', '2fa_codes'], true)) {
        // Every security change asks for the current password (throttled like the login page).
        $st = db()->prepare('SELECT * FROM users WHERE id = ?');
        $st->execute([$user['id']]);
        $me = $st->fetch();
        $form = (string)$_POST['form'];
        if (too_many_attempts()) {
            $errors[] = 'Too many failed attempts. Please wait 15 minutes and try again.';
        } elseif (!password_verify((string)($_POST['current'] ?? ''), (string)$me['password_hash'])) {
            record_failed_attempt((string)$me['username'], 'wrong password in Settings');
            $errors[] = 'Your current password is not correct.';
        } elseif ($form === 'password') {
            $new = (string)($_POST['new'] ?? '');
            if (($problem = password_problem($new, (string)$me['username'])) !== null) {
                $errors[] = $problem;
            } elseif ($new !== (string)($_POST['new2'] ?? '')) {
                $errors[] = 'The two new passwords do not match.';
            } else {
                db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([password_hash($new, PASSWORD_DEFAULT), $user['id']]);
                session_regenerate_id(true);
                bump_session_version((int)$user['id']);
                auth_log('password changed', (string)$me['username']);
                ed_security_email('Your website admin password was changed', "The admin password was changed.\nTime: " . ed_riyadh_time() . "\nDevice: " . ed_agent_summary() . "\n\nIf this was not you, contact your web developer straight away.");
                flash('Password changed. Any other devices were signed out.');
                header('Location: settings.php');
                exit;
            }
        } elseif ((int)$me['totp_on'] === 1 && !totp_check($me, (string)($_POST['code'] ?? ''))) {
            record_failed_attempt((string)$me['username'], 'wrong two-step code in Settings');
            $errors[] = 'The code from your authenticator app is not right.';
        } elseif ($form === '2fa_off') {
            db()->prepare("UPDATE users SET totp_on = 0, totp_secret = '', recovery = '' WHERE id = ?")->execute([$user['id']]);
            bump_session_version((int)$user['id']);
            auth_log('two-step login turned off', (string)$me['username']);
            ed_security_email('Two-step login was turned off', "Two-step login for the website admin was turned off.\nTime: " . ed_riyadh_time() . "\nDevice: " . ed_agent_summary() . "\n\nIf this was not you, log in, change your password and turn it back on.");
            flash('Two-step login is off. We recommend turning it back on.');
            header('Location: settings.php#two-step');
            exit;
        } else {
            [$plain, $store] = recovery_new();
            db()->prepare('UPDATE users SET recovery = ? WHERE id = ?')->execute([$store, $user['id']]);
            auth_log('new recovery codes made', (string)$me['username']);
            $_SESSION['show_codes'] = $plain;
            header('Location: settings.php#two-step');
            exit;
        }
    } elseif (($_POST['form'] ?? '') === '2fa_start') {
        $_SESSION['totp_setup'] = totp_new_secret();
        header('Location: settings.php#two-step');
        exit;
    } elseif (($_POST['form'] ?? '') === '2fa_cancel') {
        unset($_SESSION['totp_setup']);
        header('Location: settings.php#two-step');
        exit;
    } elseif (($_POST['form'] ?? '') === '2fa_confirm') {
        $secret = (string)($_SESSION['totp_setup'] ?? '');
        $step = totp_match($secret, (string)($_POST['code'] ?? ''));
        if ($step === 0) {
            $errors[] = 'That code is not right. Check the key was typed exactly, then enter the newest 6-digit code.';
        } else {
            [$plain, $store] = recovery_new();
            db()->prepare('UPDATE users SET totp_secret = ?, totp_on = 1, totp_last = ?, recovery = ? WHERE id = ?')
                ->execute([$secret, $step, $store, $user['id']]);
            unset($_SESSION['totp_setup']);
            bump_session_version((int)$user['id']);
            auth_log('two-step login turned on', (string)$user['username']);
            ed_security_email('Two-step login is on', "Two-step login for the website admin is now on. Logging in now needs your password AND a code from your phone.\nTime: " . ed_riyadh_time());
            $_SESSION['show_codes'] = $plain;
            flash('Two-step login is on. Save your recovery codes below.');
            header('Location: settings.php#two-step');
            exit;
        }
    } elseif (($_POST['form'] ?? '') === 'logout_all') {
        bump_session_version((int)$user['id']);
        auth_log('signed out all other devices', (string)$user['username']);
        flash('All other devices are signed out. This browser stays signed in.');
        header('Location: settings.php');
        exit;
    }
}

$st = db()->prepare('SELECT totp_on, recovery FROM users WHERE id = ?');
$st->execute([$user['id']]);
$sec = $st->fetch();
$twoStepOn = (int)$sec['totp_on'] === 1;
$setupSecret = $twoStepOn ? '' : (string)($_SESSION['totp_setup'] ?? '');
$showCodes = $_SESSION['show_codes'] ?? null;
unset($_SESSION['show_codes']);
$log = db()->query('SELECT * FROM auth_log ORDER BY id DESC LIMIT 15')->fetchAll();
$otpauth = $setupSecret !== '' ? 'otpauth://totp/' . rawurlencode('Edinam Destiny:' . $user['username'])
    . '?secret=' . $setupSecret . '&issuer=' . rawurlencode('Edinam Destiny') : '';

$val = function (string $k) { return h($_POST[$k] ?? setting($k)); };
admin_header('Settings', $user, 'settings');
?>
    <h1>Settings</h1>
<?php foreach ($errors as $e): ?><p class="error"><?= h($e) ?></p><?php endforeach; ?>
    <form class="card" method="post">
      <?= csrf_field() ?><input type="hidden" name="form" value="contact">
      <h2>Contact details</h2>
      <p class="muted">Shown on every page (footer and Contact page) and used for the WhatsApp buttons.</p>
      <div class="grid2">
        <label>Main phone <span class="hint">International format, e.g. +966544827315</span><input type="tel" name="phone1" value="<?= $val('phone1') ?>"></label>
        <label>Second phone<input type="tel" name="phone2" value="<?= $val('phone2') ?>"></label>
        <label>WhatsApp number <span class="hint">Digits with country code, no + or spaces</span><input type="text" name="whatsapp" value="<?= $val('whatsapp') ?>" inputmode="numeric"></label>
        <label>Google Maps link <span class="hint">“Share” link of your office on Google Maps</span><input type="url" name="maps_url" value="<?= $val('maps_url') ?>"></label>
        <label>Working hours (English) <span class="hint">e.g. Sat–Thu, 9am–10pm</span><input type="text" name="hours_en" value="<?= $val('hours_en') ?>"></label>
        <label>Working hours (Arabic)<input type="text" name="hours_ar" value="<?= $val('hours_ar') ?>" dir="rtl"></label>
        <label>Office address (English)<input type="text" name="address_en" value="<?= $val('address_en') ?>"></label>
        <label>Office address (Arabic)<input type="text" name="address_ar" value="<?= $val('address_ar') ?>" dir="rtl"></label>
      </div>
      <button class="btn btn-orange" type="submit">Save contact details</button>
    </form>
    <form class="card" method="post">
      <?= csrf_field() ?><input type="hidden" name="form" value="notify">
      <h2>Email notifications</h2>
      <p class="muted">Get an email each time someone sends a request or asks for a call-back on the website. Security alerts (new logins, password changes, many wrong passwords) are also sent here.</p>
      <div class="grid2">
        <label>Send alerts to <span class="hint">The inbox you check most</span><input type="email" name="notify_email" value="<?= $val('notify_email') ?>" placeholder="you@example.com"></label>
        <label>Send from (optional) <span class="hint">A mailbox on your domain, e.g. no-reply@yourdomain.com</span><input type="email" name="mail_from" value="<?= $val('mail_from') ?>"></label>
      </div>
      <div class="choice-group"><label><input type="checkbox" name="notify_on" value="1"<?= setting('notify_on', '1') === '1' ? ' checked' : '' ?>> Email me about new requests</label></div>
      <div class="actions">
        <button class="btn btn-orange" type="submit">Save</button>
        <button class="btn" type="submit" name="test" value="1">Save and send test email</button>
      </div>
    </form>
    <section class="card" id="two-step">
      <h2>Two-step login <?= $twoStepOn ? '<span class="pill booked">On</span>' : '<span class="pill new">Off</span>' ?></h2>
<?php if ($showCodes): ?>
      <div class="help">
        <strong>Your recovery codes. Save them now. They are shown only once.</strong>
        <p>Write them down or print this page and keep it somewhere safe (not on the same phone). If you lose your phone, each code lets you log in once.</p>
        <ul class="codes"><?php foreach ($showCodes as $c): ?><li><code><?= h($c) ?></code></li><?php endforeach; ?></ul>
      </div>
<?php endif; ?>
<?php if ($twoStepOn): ?>
      <p class="muted">Logging in needs your password <strong>and</strong> a 6-digit code from the authenticator app on your phone. Even if someone learns your password, they cannot get in. Recovery codes left: <strong><?= recovery_left($sec) ?></strong>.</p>
      <div class="grid2">
        <form method="post" autocomplete="off" class="sub-card">
          <?= csrf_field() ?><input type="hidden" name="form" value="2fa_codes">
          <h3>New recovery codes</h3>
          <label>Current password<input type="password" name="current" required autocomplete="current-password"></label>
          <label>Code from the app<input type="text" name="code" required inputmode="numeric" autocomplete="one-time-code" maxlength="6"></label>
          <button class="btn" type="submit">Make new codes</button>
        </form>
        <form method="post" autocomplete="off" class="sub-card">
          <?= csrf_field() ?><input type="hidden" name="form" value="2fa_off">
          <h3>Turn off</h3>
          <label>Current password<input type="password" name="current" required autocomplete="current-password"></label>
          <label>Code from the app<input type="text" name="code" required inputmode="numeric" autocomplete="one-time-code" maxlength="6"></label>
          <button class="btn btn-danger" type="submit">Turn off two-step login</button>
        </form>
      </div>
<?php elseif ($setupSecret !== ''): ?>
      <ol class="steps">
        <li>On your phone, install <strong>Google Authenticator</strong> or <strong>Microsoft Authenticator</strong> (free, App Store or Google Play).</li>
        <li>In the app tap <strong>+</strong> → <strong>Enter a setup key</strong>. Account name: <strong>Edinam Destiny</strong>. Key:
          <code class="secret"><?= h(trim(chunk_split($setupSecret, 4, ' '))) ?></code>
          Type: <strong>Time based</strong>. <span class="hint">On the phone itself you can tap <a href="<?= h($otpauth) ?>">open in authenticator app</a> instead.</span></li>
        <li>Type the 6-digit code the app shows:</li>
      </ol>
      <form method="post" autocomplete="off" class="inline-form">
        <?= csrf_field() ?><input type="hidden" name="form" value="2fa_confirm">
        <label>Code<input type="text" name="code" required inputmode="numeric" autocomplete="one-time-code" maxlength="6" class="code-input"></label>
        <button class="btn btn-orange" type="submit">Turn on</button>
      </form>
      <form method="post"><?= csrf_field() ?><input type="hidden" name="form" value="2fa_cancel"><button class="link-btn" type="submit">Cancel</button></form>
<?php else: ?>
      <p class="muted">Strongly recommended. After turning it on, logging in needs your password <strong>and</strong> a code from your phone, so a stolen or guessed password is not enough to get in.</p>
      <form method="post"><?= csrf_field() ?><input type="hidden" name="form" value="2fa_start"><button class="btn btn-orange" type="submit">Set up two-step login</button></form>
<?php endif; ?>
    </section>
    <form class="card" method="post" autocomplete="off">
      <?= csrf_field() ?><input type="hidden" name="form" value="password">
      <h2>Change password</h2>
      <div class="grid2">
        <label>Current password<input type="password" name="current" required autocomplete="current-password"></label>
        <span></span>
        <label>New password <span class="hint">At least 12 characters. A short sentence works well.</span><input type="password" name="new" required minlength="12" autocomplete="new-password"></label>
        <label>Repeat new password<input type="password" name="new2" required minlength="12" autocomplete="new-password"></label>
      </div>
      <button class="btn" type="submit">Change password</button>
    </form>
    <section class="card">
      <div class="head-row">
        <div>
          <h2>Recent logins</h2>
          <p class="muted">Times are Riyadh time. If you see a login that was not you, change your password and sign out other devices.</p>
        </div>
        <form method="post"><?= csrf_field() ?><input type="hidden" name="form" value="logout_all"><button class="btn btn-ghost" type="submit">Sign out all other devices</button></form>
      </div>
      <div class="table-wrap">
        <table>
          <thead><tr><th>When</th><th>What</th><th>User</th><th>Device</th><th>Internet address</th></tr></thead>
          <tbody>
<?php foreach ($log as $row): ?>
            <tr class="<?= strpos($row['event'], 'failed') === 0 || strpos($row['event'], 'paused') !== false ? 'is-warn' : '' ?>"><td><?= h(ed_riyadh_time($row['at'])) ?></td><td><?= h($row['event']) ?></td><td><?= h($row['username']) ?></td><td><?= h($row['agent']) ?></td><td><?= h($row['ip']) ?></td></tr>
<?php endforeach; ?>
<?php if (!$log): ?>
            <tr><td colspan="5" class="muted">Nothing yet.</td></tr>
<?php endif; ?>
          </tbody>
        </table>
      </div>
    </section>
<?php admin_footer();
