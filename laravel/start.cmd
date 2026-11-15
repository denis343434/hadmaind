@echo off
chcp 65001 >nul
rem Laravel-версия сайта: http://localhost:8002 и http://localhost:8002/admin
rem Нужны PHP 8.2+ и запущенный MySQL. База и данные — php artisan migrate --seed
set "PHPRC=%~dp0php.ini"
php -S 127.0.0.1:8002 -t "%~dp0public" "%~dp0server.php"
