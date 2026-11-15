<?php
// Точка входа API: все запросы /api/* попадают сюда (см. site/.htaccess).
// Код бэкенда — папка backend рядом с папкой сайта (вне публичного доступа)
// или внутри неё, если хостинг не даёт выйти выше (тогда её закрывает backend/.htaccess).
$app = is_file(dirname(__DIR__, 2) . '/backend/app.php')
    ? dirname(__DIR__, 2) . '/backend/app.php'
    : dirname(__DIR__) . '/backend/app.php';
require $app;

App\handleRequest();
