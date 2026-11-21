<?php
declare(strict_types=1);
require __DIR__ . '/../inc/admin-layout.php';
$user = require_login();

$names = ['nanny' => 'Nannies', 'cook' => 'Cooks', 'cleaner' => 'Cleaners', 'carer' => 'Caregivers', 'hosp' => 'Hospitality staff', 'maid' => 'Housemaids'];
$labels = ['ok' => 'Available now', 'ahead' => 'Book ahead', 'off' => 'Fully booked'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    foreach (ED_SERVICES as $s) {
        $val = $_POST['avail'][$s] ?? '';
        if (in_array($val, ED_STATUSES, true)) {
            save_setting('avail_' . $s, $val);
        }
    }
    flash('Availability updated. The website shows the change straight away.');
    header('Location: services.php');
    exit;
}

admin_header('Availability', $user, 'services');
?>
    <h1>Availability</h1>
    <p class="muted">Choose what each service card shows on the website (Home and Services pages, English and Arabic).</p>
    <form class="card" method="post">
      <?= csrf_field() ?>
<?php foreach ($names as $key => $name): $cur = setting('avail_' . $key, 'ok'); ?>
      <div class="svc-row">
        <strong><?= h($name) ?></strong>
        <div class="choice-group" role="radiogroup" aria-label="<?= h($name) ?>">
<?php foreach ($labels as $val => $label): ?>
          <label><input type="radio" name="avail[<?= $key ?>]" value="<?= $val ?>"<?= $cur === $val ? ' checked' : '' ?>> <?= h($label) ?></label>
<?php endforeach; ?>
        </div>
      </div>
<?php endforeach; ?>
      <div class="actions"><button class="btn btn-orange" type="submit">Save availability</button></div>
    </form>
<?php admin_footer();
