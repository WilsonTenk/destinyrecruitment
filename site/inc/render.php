<?php
/**
 * Renders public blog pages with the same design as the static site.
 * The header, footer and menus are taken from services.html at runtime,
 * so the blog always matches the rest of the site.
 */
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';

function site_base_url(): string
{
    $scheme = ed_is_https() ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
    return $scheme . '://' . $host . $dir . '/';
}

function tpl_parts(): array
{
    static $parts = null;
    if ($parts) {
        return $parts;
    }
    $tpl = (string)file_get_contents(ED_SITE_DIR . '/services.html');
    $head = substr($tpl, 0, (int)strpos($tpl, '</head>'));
    $bodyStart = (int)strpos($tpl, '<body');
    $top = substr($tpl, $bodyStart, (int)strpos($tpl, '</header>') + strlen('</header>') - $bodyStart);
    $bottom = substr($tpl, (int)strpos($tpl, '  <footer class="footer">'));
    $bottom = preg_replace('/  <div class="request-bar".*?<\/div>\n\n/s', '', $bottom) ?? $bottom;
    $top = str_replace(' aria-current="page"', '', $top);
    $top = preg_replace('/<a href="blog\.html" data-i18n="nav\.blog">/', '<a href="blog.html" aria-current="page" data-i18n="nav.blog">', $top, 1) ?? $top;
    return $parts = ['head' => $head, 'top' => $top, 'bottom' => $bottom];
}

function page_open(string $title, string $titleAr, string $desc, string $canonical, array $ld, string $ogType = 'website', string $titleKey = ''): string
{
    $p = tpl_parts();
    $h = $p['head'];
    $h = preg_replace('/<title>.*?<\/title>/s', '<title>' . h($title) . '</title>', $h, 1);
    $h = preg_replace('/<meta name="description" content="[^"]*">/', '<meta name="description" content="' . h($desc) . '">', $h, 1);
    $h = preg_replace('/<link rel="canonical" href="[^"]*">/', '<link rel="canonical" href="' . h($canonical) . '">', $h, 1);
    foreach (['og:title' => $title, 'og:description' => $desc, 'og:url' => $canonical, 'og:type' => $ogType] as $prop => $val) {
        $h = preg_replace('/<meta property="' . preg_quote($prop, '/') . '" content="[^"]*">/', '<meta property="' . $prop . '" content="' . h($val) . '">', $h, 1);
    }
    foreach (['twitter:title' => $title, 'twitter:description' => $desc] as $prop => $val) {
        $h = preg_replace('/<meta name="' . preg_quote($prop, '/') . '" content="[^"]*">/', '<meta name="' . $prop . '" content="' . h($val) . '">', $h, 1);
    }
    $h = preg_replace('/\s*<script type="application\/ld\+json">.*?<\/script>/s', '', $h);
    foreach ($ld as $obj) {
        $json = json_encode($obj, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP);
        $h .= '  <script type="application/ld+json">' . $json . "</script>\n";
    }
    $bodyAttrs = $titleKey !== '' ? 'data-title-key="' . h($titleKey) . '"' : 'data-title-ar="' . h($titleAr) . '"';
    $top = preg_replace('/<body[^>]*>/', '<body ' . $bodyAttrs . '>', $p['top'], 1);
    return $h . "</head>\n" . $top;
}

function page_close(): string
{
    return tpl_parts()['bottom'];
}

function bi(string $en, string $ar, string $tag = 'span', string $cls = ''): string
{
    $c1 = trim($cls . ' only-en');
    $c2 = trim($cls . ' only-ar');
    return "<$tag class=\"$c1\">" . h($en) . "</$tag><$tag class=\"$c2\" lang=\"ar\">" . h($ar !== '' ? $ar : $en) . "</$tag>";
}

function fmt_date_en(string $dt): string
{
    $t = strtotime($dt) ?: time();
    return gmdate('j F Y', $t);
}

function fmt_date_ar(string $dt): string
{
    $t = strtotime($dt) ?: time();
    $months = ['يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو', 'يوليو', 'أغسطس', 'سبتمبر', 'أكتوبر', 'نوفمبر', 'ديسمبر'];
    $digits = ['0' => '٠', '1' => '١', '2' => '٢', '3' => '٣', '4' => '٤', '5' => '٥', '6' => '٦', '7' => '٧', '8' => '٨', '9' => '٩'];
    $s = gmdate('j', $t) . ' ' . $months[(int)gmdate('n', $t) - 1] . ' ' . gmdate('Y', $t);
    return strtr($s, $digits);
}

function read_minutes(string $body): int
{
    $words = count(preg_split('/\s+/u', trim(strip_tags($body))) ?: []);
    return max(1, (int)ceil($words / 200));
}

function post_card(array $p): string
{
    $url = h($p['slug']) . '.html';
    $img = $p['image'] !== '' ? '<img src="' . h($p['image']) . '" width="800" height="600" alt="" loading="lazy" decoding="async">' : '';
    $min = read_minutes($p['body_en']);
    $minAr = strtr((string)read_minutes($p['body_ar'] ?: $p['body_en']), ['0' => '٠', '1' => '١', '2' => '٢', '3' => '٣', '4' => '٤', '5' => '٥', '6' => '٦', '7' => '٧', '8' => '٨', '9' => '٩']);
    return '<article class="post-card"><a class="post-card-link" href="' . $url . '">'
        . '<div class="post-card-img">' . $img . '</div><div class="post-card-body">'
        . ($p['category_en'] !== '' ? bi($p['category_en'], $p['category_ar'], 'span', 'post-cat') : '')
        . '<h2>' . bi($p['title_en'], $p['title_ar']) . '</h2>'
        . '<p>' . bi($p['excerpt_en'], $p['excerpt_ar']) . '</p>'
        . '<span class="post-meta">' . bi(fmt_date_en((string)$p['published_at']) . ' · ' . $min . ' min read', fmt_date_ar((string)$p['published_at']) . ' · ' . $minAr . ' دقائق قراءة') . '</span>'
        . '<span class="link-arrow orange">' . bi('Read article', 'اقرأ المقال') . '<span class="arrow" aria-hidden="true"></span></span>'
        . '</div></a></article>';
}

function org_ld(string $base): array
{
    return ['@type' => 'Organization', 'name' => 'Edinam Destiny Recruitment Agency', 'url' => $base,
            'logo' => ['@type' => 'ImageObject', 'url' => $base . 'assets/img/icon-512.png']];
}
