@echo off
chcp 65001 >nul
rem Локальный запуск: сайт http://localhost:4000, админка http://localhost:4000/admin
rem MySQL должен быть запущен. Вместо этого можно направить домен OSPanel на эту папку.
set "APP_ENV=development"
php -S localhost:4000 -t "%~dp0." "%~dp0backend\router.php"
