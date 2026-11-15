<?php

/**
 * Роутер для встроенного сервера PHP (замена artisan serve):
 *   php -S 127.0.0.1:8002 -t public server.php
 *
 * Существующие файлы (index.html, style.css, admin/…) отдаются как статика,
 * остальные запросы уходят в Laravel.
 */
$publicPath = __DIR__.'/public';
$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '');

if ($uri === '/admin') {
    header('Location: /admin/', true, 302);

    return true;
}

if ($uri === '/admin/') {
    header('Content-Type: text/html; charset=utf-8');
    readfile($publicPath.'/admin/index.html');

    return true;
}

if ($uri !== '/' && is_file($publicPath.$uri)) {
    return false;
}

require_once $publicPath.'/index.php';
