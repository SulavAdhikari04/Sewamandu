<?php

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/');
$file = __DIR__ . $uri;

if ($uri !== '/' && is_file($file)) {
    return false;
}

if ($uri === '/' || $uri === '') {
    require __DIR__ . '/pages/home.php';
    return true;
}

if ($uri === '/home.php') {
    header('Location: /pages/home.php', true, 302);
    return true;
}

http_response_code(404);
echo '404 Not Found';
return true;
