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
    } elseif (($_POST['form'] ?? '') === 'password') {
        $st = db()->prepare('SELECT password_hash FROM users WHERE id = ?');
        $st->execute([$user['id']]);
        $hash = (string)$st->fetchColumn();
        $new = (string)($_POST['new'] ?? '');
        if (!password_verify((string)($_POST['current'] ?? ''), $hash)) {
            $errors[] = 'Your current password is not correct.';
        } elseif (strlen($new) < 10) {
            $errors[] = 'The new password must be at least 10 characters.';
        } elseif ($new !== (string)($_POST['new2'] ?? '')) {
            $errors[] = 'The two new passwords do not match.';
        } else {
            db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([password_hash($new, PASSWORD_DEFAULT), $user['id']]);
            session_regenerate_id(true);
            flash('Password changed.');
            header('Location: settings.php');
            exit;
        }
    }
}

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
      <p class="muted">Get an email each time someone sends a request or asks for a call-back on the website.</p>
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
    <form class="card" method="post" autocomplete="off">
      <?= csrf_field() ?><input type="hidden" name="form" value="password">
      <h2>Change password</h2>
      <div class="grid2">
        <label>Current password<input type="password" name="current" required autocomplete="current-password"></label>
        <span></span>
        <label>New password <span class="hint">At least 10 characters</span><input type="password" name="new" required minlength="10" autocomplete="new-password"></label>
        <label>Repeat new password<input type="password" name="new2" required minlength="10" autocomplete="new-password"></label>
      </div>
      <button class="btn" type="submit">Change password</button>
    </form>
<?php admin_footer();
