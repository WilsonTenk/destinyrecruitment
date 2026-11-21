<?php
declare(strict_types=1);
require __DIR__ . '/../inc/bootstrap.php';
header('Location: ' . (current_user() ? 'requests.php' : 'login.php'));
