<?php
/**
 * Роутер встроенного сервера PHP для локальной работы (повторяет .htaccess):
 *   php -S localhost:4000 -t . backend/router.php
 */
$base = dirname(__DIR__);
$root = is_dir($base . '/site') ? $base . '/site' : $base;
$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/');

if (preg_match('#^/api(/|$)#', $path)) {
    require $root . '/api/index.php';
    return true;
}
if ($path === '/admin') {
    header('Location: /admin/');
    return true;
}
if (str_starts_with($path, '/admin/') && !is_file($root . $path)) {
    readfile($root . '/admin/index.html');
    return true;
}
if (is_file($root . $path)) {
    if (preg_match('#(^|/)\.|\.php$#', $path)) {
        http_response_code(404);
        return true;
    }
    return false; // статика: отдаёт встроенный сервер
}
if (!str_contains(basename($path), '.')) {
    header('Content-Type: text/html; charset=utf-8');
    readfile($root . '/index.html');
    return true;
}
http_response_code(404);
echo 'Not found';
return true;
