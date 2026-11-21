<?php
/** Public, read-only: service availability and contact details for the website. */
declare(strict_types=1);
require __DIR__ . '/../inc/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-cache');   // always fresh, so admin changes show straight away

$availability = [];
foreach (ED_SERVICES as $s) {
    $v = setting('avail_' . $s, 'ok');
    $availability[$s] = in_array($v, ED_STATUSES, true) ? $v : 'ok';
}

echo json_encode([
    'availability' => $availability,
    'contact' => [
        'phone1' => setting('phone1'),
        'phone2' => setting('phone2'),
        'whatsapp' => setting('whatsapp'),
        'hours_en' => setting('hours_en'),
        'hours_ar' => setting('hours_ar'),
        'address_en' => setting('address_en'),
        'address_ar' => setting('address_ar'),
        'maps_url' => setting('maps_url'),
    ],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
