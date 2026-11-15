@echo off
chcp 65001 >nul
rem Создаёт базу handmade_laravel, таблицы и начальные данные (товары, контакты, админ).
rem Нужны PHP 8.3+ и запущенный MySQL из OSPanel.
set "PHPRC=%~dp0php.ini"
php "%~dp0setup.php"
