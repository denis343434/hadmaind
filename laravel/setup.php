<?php

/**
 * Создаёт базу из .env (если её нет) и запускает миграции с сидерами.
 * Запускать через setup.cmd, чтобы подхватился локальный php.ini с расширениями.
 */

$root = __DIR__;
$envPath = $root.'/.env';

if (! is_file($envPath)) {
    fwrite(STDERR, "Нет файла .env — скопируйте .env.example в .env\n");
    exit(1);
}

$values = [];
foreach (file($envPath, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
    $line = trim($line);
    if ($line === '' || str_starts_with($line, '#') || ! str_contains($line, '=')) {
        continue;
    }
    [$key, $value] = array_map('trim', explode('=', $line, 2));
    $quote = $value[0] ?? '';
    if (($quote === '"' || $quote === "'") && str_ends_with($value, $quote)) {
        $value = substr($value, 1, -1);
    }
    $values[$key] = $value;
}

$host = $values['DB_HOST'] ?? '127.0.0.1';
$port = $values['DB_PORT'] ?? '3306';
$name = $values['DB_NAME'] ?? 'handmade_laravel';
$user = $values['DB_USERNAME'] ?? 'root';
$pass = $values['DB_PASSWORD'] ?? '';

try {
    $pdo = new PDO("mysql:host=$host;port=$port;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    $safeName = str_replace('`', '', $name);
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$safeName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "✔ База данных $safeName готова\n";
} catch (Throwable $e) {
    fwrite(STDERR, '✖ Не удалось создать базу: '.$e->getMessage()."\n");
    exit(1);
}

$command = escapeshellarg(PHP_BINARY).' artisan migrate --seed --force';
passthru($command, $code);
exit($code);
