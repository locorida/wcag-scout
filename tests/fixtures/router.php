<?php

// Router for PHP's built-in web server, used by the integration tests:
//   php -S 127.0.0.1:8000 -t tests/fixtures/site tests/fixtures/router.php
// Without it, the built-in server answers unknown paths with index.html and status 200.

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/';
$file = __DIR__ . '/site' . $path;

if ($path === '/' || is_file($file)) {
    return false;
}

http_response_code(404);
header('Content-Type: text/html; charset=utf-8');
echo '<!doctype html><html lang="en"><head><title>Not found</title></head><body><main><h1>Not found</h1></main></body></html>';
