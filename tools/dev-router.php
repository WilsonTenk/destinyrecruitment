<?php
/**
 * Local testing only (not deployed). Mimics the cPanel .htaccess rules for
 * PHP's built-in server:
 *   php -S localhost:8080 -t site tools/dev-router.php
 */
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/';
$root = $_SERVER['DOCUMENT_ROOT'];

if (preg_match('#^/(inc|data)(/|$)#', $path) || preg_match('#\.(sqlite|md|yml)$#', $path)
    || preg_match('#^/uploads/.*\.(php|phtml|phar|html?|js)$#i', $path)) {
    http_response_code(404);
    readfile($root . '/404.html');
    return true;
}
if ($path === '/blog.html') {
    require $root . '/blog.php';
    return true;
}
if (preg_match('#^/(blog-[a-z0-9-]+)\.html$#', $path, $m)) {
    $_GET['slug'] = $m[1];
    $_SERVER['SCRIPT_NAME'] = '/article.php';
    require $root . '/article.php';
    return true;
}
if ($path === '/') {
    return false;
}
if (is_file($root . $path)) {
    return false;
}
if (is_dir($root . $path) && is_file($root . rtrim($path, '/') . '/index.php')) {
    $_SERVER['SCRIPT_NAME'] = rtrim($path, '/') . '/index.php';
    require $root . rtrim($path, '/') . '/index.php';
    return true;
}
http_response_code(404);
readfile($root . '/404.html');
return true;
