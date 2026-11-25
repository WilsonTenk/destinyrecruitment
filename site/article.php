<?php
/* One blog article, served at /blog-<slug> on cPanel (see .htaccess). */
declare(strict_types=1);
require __DIR__ . '/inc/render.php';

$slug = isset($_GET['slug']) && is_string($_GET['slug']) ? $_GET['slug'] : '';
$p = false;
if (preg_match('/^blog-[a-z0-9-]{1,80}$/', $slug)) {
    $st = db()->prepare('SELECT * FROM posts WHERE slug = ? AND published = 1');
    $st->execute([$slug]);
    $p = $st->fetch();
}
if (!$p) {
    http_response_code(404);
    readfile(__DIR__ . '/404.html');
    exit;
}

$base = site_base_url();
$url = $base . $p['slug'];
$img = $p['image'] !== '' ? $base . $p['image'] : $base . 'assets/img/og-image.png';
$date = substr((string)$p['published_at'], 0, 10);
$ld = [
    ['@context' => 'https://schema.org', '@type' => 'BlogPosting', 'headline' => $p['title_en'], 'alternativeHeadline' => $p['title_ar'],
     'description' => $p['excerpt_en'], 'image' => $img, 'datePublished' => $date, 'dateModified' => substr((string)$p['updated_at'], 0, 10),
     'author' => org_ld($base), 'publisher' => org_ld($base), 'mainEntityOfPage' => $url, 'inLanguage' => ['en', 'ar']],
    ['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => [
        ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => $base],
        ['@type' => 'ListItem', 'position' => 2, 'name' => 'Blog', 'item' => $base . 'blog'],
        ['@type' => 'ListItem', 'position' => 3, 'name' => $p['title_en'], 'item' => $url]]],
];
$min = read_minutes($p['body_en']);
$minAr = strtr((string)read_minutes($p['body_ar'] ?: $p['body_en']), ['0' => '٠', '1' => '١', '2' => '٢', '3' => '٣', '4' => '٤', '5' => '٥', '6' => '٦', '7' => '٧', '8' => '٨', '9' => '٩']);
$takeEn = lines_list($p['takeaways_en']);
$takeAr = lines_list($p['takeaways_ar']);
$related = db()->prepare('SELECT * FROM posts WHERE published = 1 AND id != ? ORDER BY published_at DESC LIMIT 2');
$related->execute([$p['id']]);
$related = $related->fetchAll();

header('Content-Type: text/html; charset=utf-8');
echo page_open($p['title_en'] . ' – Edinam Destiny', $p['title_ar'] !== '' ? $p['title_ar'] . ' – إدينام ديستني' : '', $p['excerpt_en'], $url, $ld, 'article');
?>

      <div class="page-hero-copy">
        <nav class="breadcrumb" aria-label="Breadcrumb"><a href="blog" data-i18n="nav.blog">Blog</a><?php if ($p['category_en'] !== ''): ?><span aria-hidden="true">/</span><?= bi($p['category_en'], $p['category_ar']) ?><?php endif; ?></nav>
        <h1><?= bi($p['title_en'], $p['title_ar']) ?></h1>
        <p class="post-meta-light"><?= bi(fmt_date_en((string)$p['published_at']) . ' · ' . $min . ' min read', fmt_date_ar((string)$p['published_at']) . ' · ' . $minAr . ' دقائق قراءة') ?></p>
      </div>
    </div>
  </section>

  <main>
    <article class="container post">
<?php if ($p['image'] !== ''): ?>
      <figure class="post-hero-img"><img src="<?= h($p['image']) ?>" width="800" height="600" alt="<?= h($p['image_alt_en']) ?>" data-alt-ar="<?= h($p['image_alt_ar']) ?>" fetchpriority="high"></figure>
<?php endif; ?>
      <div class="prose only-en"><?= render_body($p['body_en']) ?></div>
      <div class="prose only-ar" lang="ar"><?= render_body($p['body_ar'] !== '' ? $p['body_ar'] : $p['body_en']) ?></div>
<?php if ($takeEn): ?>
      <aside class="takeaways" aria-label="Key takeaways">
        <h2 data-i18n="blog.takeaways">Key takeaways</h2>
        <ul class="checks only-en"><?php foreach ($takeEn as $t) { echo '<li>' . h($t) . '</li>'; } ?></ul>
        <ul class="checks only-ar" lang="ar"><?php foreach (($takeAr ?: $takeEn) as $t) { echo '<li>' . h($t) . '</li>'; } ?></ul>
      </aside>
<?php endif; ?>
    </article>

    <section class="section" style="padding-top: 48px;">
      <div class="container">
        <div class="cta-band">
          <div>
            <h2><?= bi($p['cta_en'] !== '' ? $p['cta_en'] : 'Need help at home?', $p['cta_ar'] !== '' ? $p['cta_ar'] : 'تحتاج إلى مساعدة في المنزل؟') ?></h2>
            <p data-i18n="blog.cta.text">Tell us about your home and we’ll send you a plan and a price made for you.</p>
          </div>
          <a class="btn btn-white" href="services#request"><span data-i18n="svcp.build">Build your request</span><span class="arrow" aria-hidden="true"></span></a>
        </div>
      </div>
    </section>
<?php if ($related): ?>

    <section class="section" style="padding-top: 0;" aria-labelledby="more-title">
      <div class="container">
        <div class="section-head"><h2 id="more-title" data-i18n="blog.more">More from the blog</h2></div>
        <div class="grid grid-2 blog-grid">
<?php foreach ($related as $r) { echo '          ' . post_card($r) . "\n"; } ?>
        </div>
      </div>
    </section>
<?php endif; ?>
  </main>

<?php echo page_close();
