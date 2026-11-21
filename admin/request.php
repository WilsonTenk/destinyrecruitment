<?php
declare(strict_types=1);
require __DIR__ . '/../inc/admin-layout.php';
$user = require_login();

$id = (int)($_GET['id'] ?? 0);
$st = db()->prepare('SELECT * FROM requests WHERE id = ?');
$st->execute([$id]);
$r = $st->fetch();
if (!$r) {
    http_response_code(404);
    admin_header('Not found', $user, 'requests');
    echo '<p class="error">That request does not exist.</p><p><a href="requests.php">Back to requests</a></p>';
    admin_footer();
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (isset($_POST['delete'])) {
        if (($_POST['confirm'] ?? '') === 'yes') {
            db()->prepare('DELETE FROM requests WHERE id = ?')->execute([$id]);
            flash('Request deleted.');
            header('Location: requests.php');
            exit;
        }
        flash('Tick the box to confirm deleting.');
    } else {
        $status = in_array($_POST['status'] ?? '', ED_REQUEST_STATUSES, true) ? $_POST['status'] : $r['status'];
        db()->prepare('UPDATE requests SET status = ?, admin_note = ?, updated_at = ? WHERE id = ?')
            ->execute([$status, clean_text($_POST['admin_note'] ?? '', 4000), ed_now(), $id]);
        flash('Saved.');
    }
    header('Location: request.php?id=' . $id);
    exit;
}

$wa = preg_replace('/\D+/', '', (string)$r['phone']);
if ($wa !== '' && strpos($wa, '0') === 0) {
    $wa = '966' . substr($wa, 1);
}
$fields = [
    'Received' => $r['created_at'] . ' UTC',
    'Form' => $r['kind'] === 'build' ? 'Build your request' : 'Call-back request',
    'Name' => $r['name'], 'Phone' => $r['phone'], 'Services' => $r['services'], 'Home' => $r['home'],
    'Extras' => $r['extras'], 'Care for' => $r['care'], 'Schedule' => $r['schedule'], 'Area' => $r['area'],
    'Start date' => $r['start_date'], 'Notes / message' => $r['notes'], 'Language' => $r['lang'] === 'ar' ? 'Arabic' : 'English',
];

admin_header('Request #' . $id, $user, 'requests');
?>
    <p><a href="requests.php">← All requests</a></p>
    <div class="head-row">
      <h1>Request #<?= $id ?> <span class="pill <?= h($r['status']) ?>"><?= h(ucfirst($r['status'])) ?></span></h1>
<?php if ($wa !== ''): ?>
      <a class="btn" href="https://wa.me/<?= h($wa) ?>" target="_blank" rel="noopener">Reply on WhatsApp</a>
<?php endif; ?>
    </div>
    <div class="card">
      <dl class="details">
<?php foreach ($fields as $label => $value): if ((string)$value === '') { continue; } ?>
        <dt><?= h($label) ?></dt><dd><?= h((string)$value) ?></dd>
<?php endforeach; ?>
      </dl>
    </div>
    <form class="card" method="post">
      <?= csrf_field() ?>
      <h2>Follow-up</h2>
      <label>Status
        <select name="status">
<?php foreach (ED_REQUEST_STATUSES as $s): ?>
          <option value="<?= $s ?>"<?= $r['status'] === $s ? ' selected' : '' ?>><?= ucfirst($s) ?></option>
<?php endforeach; ?>
        </select>
      </label>
      <label>Private note <span class="hint">Only visible here, for example the price you quoted.</span><textarea name="admin_note"><?= h($r['admin_note']) ?></textarea></label>
      <button class="btn btn-orange" type="submit">Save</button>
    </form>
    <form class="card" method="post">
      <?= csrf_field() ?>
      <h2>Delete</h2>
      <div class="choice-group"><label><input type="checkbox" name="confirm" value="yes"> Yes, permanently delete this request</label></div>
      <div class="actions"><button class="btn btn-danger" type="submit" name="delete" value="1">Delete request</button></div>
    </form>
<?php admin_footer();
