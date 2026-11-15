@echo off
chcp 65001 >nul
rem Установка базы: таблицы, каталог изделий и админ из backend\.env.
rem Нужны PHP 8.1+ в PATH и запущенный MySQL/MariaDB (например, из OSPanel).
php "%~dp0backend\bin\install.php"
