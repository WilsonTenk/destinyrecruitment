<?php
declare(strict_types=1);
require __DIR__ . '/../inc/admin-layout.php';
$user = require_login();

$posts = db()->query('SELECT id, slug, title_en, published, published_at, updated_at FROM posts ORDER BY COALESCE(published_at, created_at) DESC, id DESC')->fetchAll();

admin_header('Blog', $user, 'posts');
?>
    <div class="head-row">
      <div>
        <h1>Blog</h1>
        <p class="muted">Write and publish articles in English and Arabic. Published articles appear on the Blog page straight away.</p>
      </div>
      <a class="btn btn-orange" href="post-edit.php">+ New article</a>
    </div>
    <div class="card table-wrap">
<?php if (!$posts): ?>
      <p class="muted">No articles yet.</p>
<?php else: ?>
      <table>
        <thead><tr><th>Title</th><th>Status</th><th>Published</th><th>Last edited</th><th></th></tr></thead>
        <tbody>
<?php foreach ($posts as $p): ?>
          <tr>
            <td><strong><?= h($p['title_en']) ?></strong><span class="hint"><?= h($p['slug']) ?>.html</span></td>
            <td><span class="pill <?= $p['published'] ? 'published' : 'draft' ?>"><?= $p['published'] ? 'Published' : 'Draft' ?></span></td>
            <td><?= h($p['published_at'] ? substr($p['published_at'], 0, 10) : '—') ?></td>
            <td><?= h(substr($p['updated_at'], 0, 10)) ?></td>
            <td><a href="post-edit.php?id=<?= (int)$p['id'] ?>">Edit</a><?php if ($p['published']): ?> · <a href="../<?= h($p['slug']) ?>.html" target="_blank" rel="noopener">View</a><?php endif; ?></td>
          </tr>
<?php endforeach; ?>
        </tbody>
      </table>
<?php endif; ?>
    </div>
<?php admin_footer();
