<?php
declare(strict_types=1);

namespace App;

use PDO;

/**
 * Тонкая обёртка над PDO (MySQL / MariaDB). Время в базе — UTC (сессия переводится в +00:00),
 * колонки названы в camelCase, чтобы строки уходили в JSON без переименования.
 */
final class Db
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo) return self::$pdo;
        if (env('DB_NAME') === '') throw new \RuntimeException('Не задан DB_NAME в backend/.env');

        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', env('DB_HOST'), env('DB_PORT'), env('DB_NAME'));
        self::$pdo = new PDO($dsn, env('DB_USER'), env('DB_PASS'), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false, // числа приходят числами
            PDO::MYSQL_ATTR_FOUND_ROWS => true, // rowCount у UPDATE = найденные строки, а не изменённые
        ]);
        self::$pdo->exec("SET time_zone = '+00:00'");
        return self::$pdo;
    }

    public static function all(string $sql, array $params = []): array
    {
        $st = self::pdo()->prepare($sql);
        $st->execute($params);
        return $st->fetchAll();
    }

    public static function one(string $sql, array $params = []): ?array
    {
        $st = self::pdo()->prepare($sql);
        $st->execute($params);
        return $st->fetch() ?: null;
    }

    public static function value(string $sql, array $params = []): mixed
    {
        $st = self::pdo()->prepare($sql);
        $st->execute($params);
        $v = $st->fetchColumn();
        return $v === false ? null : $v;
    }

    /** Выполнить запрос, вернуть число затронутых строк. */
    public static function exec(string $sql, array $params = []): int
    {
        $st = self::pdo()->prepare($sql);
        $st->execute($params);
        return $st->rowCount();
    }

    public static function insert(string $table, array $data): int
    {
        $cols = array_keys($data);
        $sql = sprintf(
            'INSERT INTO `%s` (%s) VALUES (%s)',
            $table,
            implode(', ', array_map(fn ($c) => "`$c`", $cols)),
            implode(', ', array_fill(0, count($cols), '?')),
        );
        self::exec($sql, array_map([self::class, 'scalar'], array_values($data)));
        return (int) self::pdo()->lastInsertId();
    }

    /** UPDATE по id; запись не найдена → 404. Пустой $data — только проверка существования. */
    public static function update(string $table, int $id, array $data): void
    {
        if (!$data) {
            self::findOrFail($table, $id);
            return;
        }
        $set = implode(', ', array_map(fn ($c) => "`$c` = ?", array_keys($data)));
        $params = [...array_map([self::class, 'scalar'], array_values($data)), $id];
        if (self::exec("UPDATE `$table` SET $set WHERE id = ?", $params) === 0) throw HttpError::notFound('Запись не найдена');
    }

    public static function delete(string $table, int $id): void
    {
        if (self::exec("DELETE FROM `$table` WHERE id = ?", [$id]) === 0) throw HttpError::notFound('Запись не найдена');
    }

    public static function findOrFail(string $table, int $id): array
    {
        return self::one("SELECT * FROM `$table` WHERE id = ?", [$id]) ?? throw HttpError::notFound('Запись не найдена');
    }

    /** @template T @param callable(): T $fn @return T */
    public static function tx(callable $fn): mixed
    {
        $pdo = self::pdo();
        $pdo->beginTransaction();
        try {
            $result = $fn();
            $pdo->commit();
            return $result;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    /** "?, ?, ?" для IN (...). */
    public static function marks(array $values): string
    {
        return implode(', ', array_fill(0, max(1, count($values)), '?'));
    }

    private static function scalar(mixed $v): mixed
    {
        return is_bool($v) ? (int) $v : $v;
    }
}

// ─── Приведение строк из базы к JSON-виду ────────────────────────────

/**
 * Приводит поля строки: bool — TINYINT → true/false, date — UTC DATETIME → ISO "…Z",
 * json — строка → значение (объекты остаются объектами, а не массивами).
 *
 * @param array<string, 'bool'|'date'|'json'> $casts
 */
function cast(?array $row, array $casts): ?array
{
    if ($row === null) return null;
    foreach ($casts as $key => $type) {
        if (!array_key_exists($key, $row) || $row[$key] === null) continue;
        $row[$key] = match ($type) {
            'bool' => (bool) $row[$key],
            'date' => isoUtc($row[$key]),
            'json' => json_decode((string) $row[$key]),
        };
    }
    return $row;
}

function castAll(array $rows, array $casts): array
{
    return array_map(fn ($r) => cast($r, $casts), $rows);
}

/** "2026-10-10 15:00:00.000" (UTC) → "2026-10-10T15:00:00.000Z" — как отдавал Prisma. */
function isoUtc(string $value): string
{
    return (new \DateTimeImmutable($value, new \DateTimeZone('UTC')))->format('Y-m-d\TH:i:s.v\Z');
}

/** Unix-время → строка для DATETIME (UTC). */
function dbTime(int $ts): string
{
    return gmdate('Y-m-d H:i:s', $ts);
}

/** UTC DATETIME → Unix-время. */
function fromDbTime(string $value): int
{
    return (new \DateTimeImmutable($value, new \DateTimeZone('UTC')))->getTimestamp();
}

function toJson(mixed $value): string
{
    return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
}
