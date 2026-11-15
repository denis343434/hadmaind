@echo off
chcp 65001 >nul
rem Обёртка над artisan с локальным php.ini (нужны openssl, mbstring, curl, fileinfo, zip, pdo_mysql).
set "PHPRC=%~dp0php.ini"
php "%~dp0artisan" %*
