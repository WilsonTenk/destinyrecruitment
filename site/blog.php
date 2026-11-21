<?php
/* Blog list, served at /blog.html on cPanel (see .htaccess). */
declare(strict_types=1);
require __DIR__ . '/inc/render.php';

$base = site_base_url();
$posts = db()->query("SELECT * FROM posts WHERE published = 1 ORDER BY published_at DESC, id DESC")->fetchAll();

$ld = [[
    '@context' => 'https://schema.org', '@type' => 'Blog', 'name' => 'Edinam Destiny Recruitment blog', 'url' => $base . 'blog.html',
    'publisher' => org_ld($base), 'inLanguage' => ['en', 'ar'],
    'blogPost' => array_map(function ($p) use ($base) {
        return ['@type' => 'BlogPosting', 'headline' => $p['title_en'], 'url' => $base . $p['slug'] . '.html', 'datePublished' => substr((string)$p['published_at'], 0, 10)];
    }, $posts),
]];

header('Content-Type: text/html; charset=utf-8');
echo page_open('Blog – Edinam Destiny Recruitment', '', 'Practical guides for Riyadh families on nannies, housemaids, caregivers and choosing the right home help.', $base . 'blog.html', $ld, 'website', 'title.blog');
?>

      <div class="page-hero-copy">
        <span class="eyebrow" data-i18n="blog.eyebrow">Blog</span>
        <h1 data-i18n="blog.h1">Guides for a <span class="serif u">happier home</span></h1>
        <p data-i18n="blog.lead">Practical advice for Riyadh families on choosing and working with nannies, housemaids, cooks and caregivers.</p>
      </div>
    </div>
  </section>

  <main class="section">
    <div class="container">
<?php if (!$posts): ?>
      <p class="lead" style="text-align:center"><?= bi('New articles are coming soon.', 'مقالات جديدة قريبًا.') ?></p>
<?php else: ?>
      <div class="grid grid-3 blog-grid">
<?php foreach ($posts as $p) { echo '        ' . post_card($p) . "\n"; } ?>
      </div>
<?php endif; ?>
    </div>
  </main>

<?php echo page_close();
