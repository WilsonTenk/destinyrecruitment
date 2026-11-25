<?php
declare(strict_types=1);
require __DIR__ . '/../inc/admin-layout.php';
$user = require_login();

$id = (int)($_GET['id'] ?? 0);
$post = null;
if ($id) {
    $st = db()->prepare('SELECT * FROM posts WHERE id = ?');
    $st->execute([$id]);
    $post = $st->fetch() ?: null;
    if (!$post) {
        http_response_code(404);
        exit('Article not found.');
    }
}
$fields = ['title_en' => 200, 'title_ar' => 200, 'excerpt_en' => 400, 'excerpt_ar' => 400, 'body_en' => 40000, 'body_ar' => 40000,
           'takeaways_en' => 2000, 'takeaways_ar' => 2000, 'category_en' => 60, 'category_ar' => 60, 'cta_en' => 120, 'cta_ar' => 120,
           'image_alt_en' => 200, 'image_alt_ar' => 200];
$errors = [];

/** Checks an uploaded photo and saves a clean WebP copy (max 1600px). Returns the site path or ''. */
function save_upload(array $file, array &$errors): string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return '';
    }
    if ($file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
        $errors[] = 'The photo could not be uploaded. Try a smaller file.';
        return '';
    }
    if ($file['size'] > 8 * 1024 * 1024) {
        $errors[] = 'The photo is larger than 8 MB.';
        return '';
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    $loaders = ['image/jpeg' => 'imagecreatefromjpeg', 'image/png' => 'imagecreatefrompng', 'image/webp' => 'imagecreatefromwebp'];
    if (!isset($loaders[$mime]) || !@getimagesize($file['tmp_name'])) {
        $errors[] = 'Please upload a JPG, PNG or WebP photo.';
        return '';
    }
    if (!function_exists('imagewebp')) {
        $errors[] = 'This server cannot process images (the PHP GD extension is missing).';
        return '';
    }
    $src = @$loaders[$mime]($file['tmp_name']);
    if (!$src) {
        $errors[] = 'That photo could not be read.';
        return '';
    }
    $w = imagesx($src);
    $hgt = imagesy($src);
    $scale = min(1, 1600 / max($w, $hgt));
    $nw = (int)round($w * $scale);
    $nh = (int)round($hgt * $scale);
    $dst = imagecreatetruecolor($nw, $nh);
    imagealphablending($dst, false);
    imagesavealpha($dst, true);
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $hgt);
    if (!is_dir(ED_UPLOAD_DIR)) {
        @mkdir(ED_UPLOAD_DIR, 0755, true);
    }
    $name = gmdate('Ymd') . '-' . bin2hex(random_bytes(6)) . '.webp';
    $ok = imagewebp($dst, ED_UPLOAD_DIR . '/' . $name, 82);
    imagedestroy($src);
    imagedestroy($dst);
    if (!$ok) {
        $errors[] = 'The photo could not be saved. Check that the uploads folder is writable.';
        return '';
    }
    return ED_UPLOAD_URL . '/' . $name;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    if (isset($_POST['delete']) && $post) {
        if (($_POST['confirm_delete'] ?? '') === 'yes') {
            db()->prepare('DELETE FROM posts WHERE id = ?')->execute([$post['id']]);
            flash('Article deleted.');
            header('Location: posts.php');
            exit;
        }
        $errors[] = 'Tick the box to confirm deleting this article.';
    } else {
        $data = [];
        foreach ($fields as $f => $max) {
            $data[$f] = clean_text($_POST[$f] ?? '', $max);
        }
        if ($data['title_en'] === '') {
            $errors[] = 'The English title is required.';
        }
        $slug = 'blog-' . slugify(clean_text($_POST['slug'] ?? '', 80) !== '' ? preg_replace('/^blog-/', '', (string)$_POST['slug']) : $data['title_en']);
        $clash = db()->prepare('SELECT id FROM posts WHERE slug = ? AND id != ?');
        $clash->execute([$slug, $post['id'] ?? 0]);
        if ($clash->fetch()) {
            $errors[] = 'Another article already uses that web address. Change the address or the title.';
        }
        $image = $post['image'] ?? '';
        $newImage = save_upload($_FILES['image'] ?? ['error' => UPLOAD_ERR_NO_FILE], $errors);
        if ($newImage !== '') {
            $image = $newImage;
        }
        $published = !empty($_POST['published']) ? 1 : 0;

        if (!$errors) {
            $now = ed_now();
            $publishedAt = $post['published_at'] ?? null;
            if ($published && !$publishedAt) {
                $publishedAt = $now;
            }
            $cols = array_keys($data);
            if ($post) {
                $set = implode(', ', array_map(function ($c) { return "$c = ?"; }, $cols));
                db()->prepare("UPDATE posts SET $set, slug = ?, image = ?, published = ?, published_at = ?, updated_at = ? WHERE id = ?")
                    ->execute(array_merge(array_values($data), [$slug, $image, $published, $publishedAt, $now, $post['id']]));
                $id = (int)$post['id'];
            } else {
                db()->prepare('INSERT INTO posts(' . implode(',', $cols) . ', slug, image, published, published_at, created_at, updated_at) VALUES ('
                    . implode(',', array_fill(0, count($cols), '?')) . ', ?, ?, ?, ?, ?, ?)')
                    ->execute(array_merge(array_values($data), [$slug, $image, $published, $publishedAt, $now, $now]));
                $id = (int)db()->lastInsertId();
            }
            flash($published ? 'Saved and published.' : 'Saved as a draft.');
            header('Location: post-edit.php?id=' . $id);
            exit;
        }
        $post = array_merge($post ?? [], $data, ['slug' => $slug, 'image' => $image, 'published' => $published]);
    }
}

$v = function (string $f) use ($post): string { return h((string)($post[$f] ?? '')); };
admin_header($post && !empty($post['id']) ? 'Edit article' : 'New article', $user, 'posts');
?>
    <p><a href="posts.php">← All articles</a></p>
    <h1><?= $post && !empty($post['id']) ? 'Edit article' : 'New article' ?></h1>
<?php foreach ($errors as $e): ?><p class="error"><?= h($e) ?></p><?php endforeach; ?>
    <p class="help"><strong>Writing tips:</strong> leave an empty line between paragraphs. Start a line with <code>## </code> for a heading, <code>- </code> for a bullet point. Use <code>**bold**</code> and <code>[link text](https://...)</code>.</p>
    <form method="post" enctype="multipart/form-data">
      <?= csrf_field() ?>
      <div class="card">
        <h2>Title and summary</h2>
        <div class="grid2">
          <label>Title (English) *<input type="text" name="title_en" value="<?= $v('title_en') ?>" required maxlength="200"></label>
          <label>Title (Arabic)<input type="text" name="title_ar" value="<?= $v('title_ar') ?>" dir="rtl" maxlength="200"></label>
          <label>Short summary (English) <span class="hint">Shown on the Blog page and in Google.</span><textarea name="excerpt_en" maxlength="400"><?= $v('excerpt_en') ?></textarea></label>
          <label>Short summary (Arabic)<textarea name="excerpt_ar" dir="rtl" maxlength="400"><?= $v('excerpt_ar') ?></textarea></label>
          <label>Category (English) <span class="hint">For example: Childcare</span><input type="text" name="category_en" value="<?= $v('category_en') ?>" maxlength="60"></label>
          <label>Category (Arabic)<input type="text" name="category_ar" value="<?= $v('category_ar') ?>" dir="rtl" maxlength="60"></label>
        </div>
      </div>
      <div class="card">
        <h2>Article text</h2>
        <div class="grid2">
          <label>Article (English)<textarea class="tall" name="body_en"><?= $v('body_en') ?></textarea></label>
          <label>Article (Arabic)<textarea class="tall" name="body_ar" dir="rtl"><?= $v('body_ar') ?></textarea></label>
          <label>Key takeaways (English) <span class="hint">One per line.</span><textarea name="takeaways_en"><?= $v('takeaways_en') ?></textarea></label>
          <label>Key takeaways (Arabic)<textarea name="takeaways_ar" dir="rtl"><?= $v('takeaways_ar') ?></textarea></label>
          <label>Banner question (English) <span class="hint">For example: Looking for a nanny?</span><input type="text" name="cta_en" value="<?= $v('cta_en') ?>" maxlength="120"></label>
          <label>Banner question (Arabic)<input type="text" name="cta_ar" value="<?= $v('cta_ar') ?>" dir="rtl" maxlength="120"></label>
        </div>
      </div>
      <div class="card">
        <h2>Photo</h2>
<?php if (!empty($post['image'])): ?>
        <img class="thumb" src="../<?= $v('image') ?>" alt="">
<?php endif; ?>
        <label><?= !empty($post['image']) ? 'Replace photo' : 'Upload photo' ?> <span class="hint">JPG, PNG or WebP, up to 8 MB. Use your own photos or ones you have the right to use.</span><input type="file" name="image" accept="image/jpeg,image/png,image/webp"></label>
        <div class="grid2">
          <label>Photo description (English) <span class="hint">For blind visitors and Google.</span><input type="text" name="image_alt_en" value="<?= $v('image_alt_en') ?>" maxlength="200"></label>
          <label>Photo description (Arabic)<input type="text" name="image_alt_ar" value="<?= $v('image_alt_ar') ?>" dir="rtl" maxlength="200"></label>
        </div>
      </div>
      <div class="card">
        <h2>Publishing</h2>
        <label>Web address <span class="hint">Leave empty to make one from the English title. Changing it later breaks old links.</span><input type="text" name="slug" value="<?= $v('slug') ?>" maxlength="80" placeholder="blog-your-article-title"></label>
        <div class="choice-group"><label><input type="checkbox" name="published" value="1"<?= !empty($post['published']) ? ' checked' : '' ?>> Published (visible on the website)</label></div>
        <div class="actions">
          <button class="btn btn-orange" type="submit">Save</button>
<?php if (!empty($post['published']) && !empty($post['slug'])): ?>
          <a class="btn btn-ghost" href="../<?= $v('slug') ?>" target="_blank" rel="noopener">View on site</a>
<?php endif; ?>
        </div>
      </div>
    </form>
<?php if ($post && !empty($post['id'])): ?>
    <form class="card" method="post">
      <?= csrf_field() ?>
      <h2>Delete article</h2>
      <div class="choice-group"><label><input type="checkbox" name="confirm_delete" value="yes"> Yes, permanently delete this article</label></div>
      <div class="actions"><button class="btn btn-danger" type="submit" name="delete" value="1">Delete</button></div>
    </form>
<?php endif;
admin_footer();
