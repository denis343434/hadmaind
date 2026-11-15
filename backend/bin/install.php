<?php
declare(strict_types=1);

/**
 * Установка базы: таблицы + данные магазина + админ из .env. Повторный запуск ничего не дублирует:
 * карточка заведения и админ создаются, только если их нет, каталог — только в пустой базе.
 *
 *   php backend/bin/install.php                 — создать/дополнить базу
 *   php backend/bin/install.php --replace-shop  — заменить каталог на данные из sql/seed.sql (правки из админки пропадут)
 */

namespace App;

if (PHP_SAPI !== 'cli') exit("Только из командной строки\n");
require dirname(__DIR__) . '/app.php';
require dirname(__DIR__) . '/modules/auth.php'; // ensureFirstAdmin()

/** Выполнить SQL-текст по одному запросу. */
function runSql(string $sql): void
{
    $sql = preg_replace('/^\s*--.*$/m', '', $sql) ?? $sql;
    foreach (preg_split('/;\s*(?:\R|$)/', $sql) ?: [] as $statement) {
        if (trim($statement) !== '') Db::pdo()->exec($statement);
    }
}

function createDatabaseIfMissing(): void
{
    try {
        Db::pdo();
    } catch (\PDOException $e) {
        if ((int) ($e->errorInfo[1] ?? 0) !== 1049) throw $e; // 1049 — базы нет
        $dsn = sprintf('mysql:host=%s;port=%d;charset=utf8mb4', env('DB_HOST'), env('DB_PORT'));
        (new \PDO($dsn, env('DB_USER'), env('DB_PASS')))
            ->exec('CREATE DATABASE `' . str_replace('`', '', env('DB_NAME')) . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        echo '✔ Создана база ' . env('DB_NAME') . "\n";
    }
}

try {
    createDatabaseIfMissing();
    runSql((string) file_get_contents(BACKEND_DIR . '/sql/schema.sql'));
    echo "✔ Таблицы\n";

    $sections = [];
    $parts = preg_split('/^-- @section (\w+)\s*$/m', (string) file_get_contents(BACKEND_DIR . '/sql/seed.sql'), -1, PREG_SPLIT_DELIM_CAPTURE);
    for ($i = 1; $i < count($parts); $i += 2) $sections[$parts[$i]] = $parts[$i + 1];

    runSql($sections['site']);
    echo "✔ Карточка заведения\n";

    if (in_array('--replace-menu', $argv, true)) {
        Db::exec('DELETE FROM menu_categories'); // блюда удаляются каскадно
        echo "✔ Старое меню удалено\n";
    }
    if (in_array('--replace-shop', $argv, true)) {
        Db::exec('DELETE FROM shop_categories'); // товары удаляются каскадно
        echo "✔ Старый каталог удалён\n";
    }
    $hasSql = fn (string $name) => trim(preg_replace('/^\s*--.*$/m', '', $sections[$name] ?? '') ?? '') !== '';
    if ($hasSql('menu') && !Db::value('SELECT COUNT(*) FROM menu_categories')) {
        Db::tx(fn () => runSql($sections['menu']));
        echo "✔ Меню\n";
    }
    if ($hasSql('shop') && !Db::value('SELECT COUNT(*) FROM shop_products')) {
        Db::tx(fn () => runSql($sections['shop']));
        echo "✔ Каталог изделий\n";
    }
    if ($hasSql('booking') && !Db::value('SELECT COUNT(*) FROM resources')) {
        Db::tx(fn () => runSql($sections['booking']));
        echo "✔ Арена, услуги и график\n";
    }

    ensureFirstAdmin();
    $admin = Db::value('SELECT email FROM admins ORDER BY id LIMIT 1');
    echo $admin ? "✔ Админ: $admin\n" : "! Админ не создан: задайте ADMIN_EMAIL и ADMIN_PASSWORD в backend/.env\n";
} catch (\Throwable $e) {
    fwrite(STDERR, '✖ ' . $e->getMessage() . "\n");
    exit(1);
}
