<?php
declare(strict_types=1);

namespace App;

/** JWT (HS256) для админки: токен в заголовке Authorization: Bearer <token>. */
final class Auth
{
    public static function sign(int $adminId, string $email): string
    {
        $now = time();
        $payload = ['sub' => $adminId, 'email' => $email, 'iat' => $now, 'exp' => $now + self::ttl(env('JWT_EXPIRES_IN'))];
        $head = self::b64(toJson(['alg' => 'HS256', 'typ' => 'JWT']));
        $body = self::b64(toJson($payload));
        return "$head.$body." . self::b64(hash_hmac('sha256', "$head.$body", self::secret(), true));
    }

    /** Middleware: проверяет токен и кладёт ['sub', 'email'] в $req->admin. */
    public static function requireAdmin(Request $req): void
    {
        $header = $req->header('Authorization');
        if (!str_starts_with($header, 'Bearer ')) throw HttpError::unauthorized();

        $payload = self::verify(substr($header, 7));
        if (!$payload) throw HttpError::unauthorized('Токен недействителен или истёк');
        $req->admin = ['sub' => (int) $payload['sub'], 'email' => (string) ($payload['email'] ?? '')];
    }

    private static function verify(string $token): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) return null;
        [$head, $body, $sig] = $parts;
        $expected = self::b64(hash_hmac('sha256', "$head.$body", self::secret(), true));
        if (!hash_equals($expected, $sig)) return null;

        $payload = json_decode(self::unb64($body), true);
        if (!is_array($payload) || !isset($payload['sub'], $payload['exp']) || $payload['exp'] < time()) return null;
        return $payload;
    }

    /** bcrypt-хеши из старого Node-бэкенда ($2a$/$2b$) совместимы с password_verify. */
    public static function checkPassword(string $password, string $hash): bool
    {
        if (preg_match('/^\$2[ab]\$/', $hash)) $hash = '$2y$' . substr($hash, 4);
        return password_verify($password, $hash);
    }

    public static function hashPassword(string $password): string
    {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]);
    }

    private static function secret(): string
    {
        $secret = env('JWT_SECRET');
        if (strlen($secret) < 32) throw new \RuntimeException('JWT_SECRET в backend/.env должен быть не короче 32 символов');
        return $secret;
    }

    /** "7d", "12h", "30m", "3600" → секунды. */
    private static function ttl(string $value): int
    {
        if (!preg_match('/^(\d+)\s*([smhd]?)$/', trim($value), $m)) return 7 * 86400;
        return (int) $m[1] * ['' => 1, 's' => 1, 'm' => 60, 'h' => 3600, 'd' => 86400][$m[2]];
    }

    private static function b64(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function unb64(string $data): string
    {
        return (string) base64_decode(strtr($data, '-_', '+/'));
    }
}

/**
 * Защита публичных форм. Счётчики лежат в MySQL (таблица rate_limits) —
 * на обычном хостинге нет памяти процесса между запросами.
 */
final class RateLimit
{
    /** Лимит на формы (заявки, брони): 10 отправок за 15 минут с одного IP. */
    public static function forms(Request $req): void
    {
        self::hit($req, 'form', 10, 15 * 60, 'Слишком много запросов, попробуйте позже');
    }

    public static function login(Request $req): void
    {
        self::hit($req, 'login', 20, 15 * 60, 'Слишком много попыток входа, попробуйте позже');
    }

    private static function hit(Request $req, string $bucket, int $limit, int $windowSec, string $message): void
    {
        $key = hash('sha256', $bucket . '|' . $req->ip());
        Db::exec(
            'INSERT INTO rate_limits (`key`, hits, resetAt) VALUES (?, 1, UTC_TIMESTAMP() + INTERVAL ? SECOND)
             ON DUPLICATE KEY UPDATE
               hits = IF(resetAt <= UTC_TIMESTAMP(), 1, hits + 1),
               resetAt = IF(resetAt <= UTC_TIMESTAMP(), UTC_TIMESTAMP() + INTERVAL ? SECOND, resetAt)',
            [$key, $windowSec, $windowSec],
        );
        if (random_int(1, 100) === 1) Db::exec('DELETE FROM rate_limits WHERE resetAt <= UTC_TIMESTAMP()');

        $row = Db::one('SELECT hits, TIMESTAMPDIFF(SECOND, UTC_TIMESTAMP(), resetAt) AS ttl FROM rate_limits WHERE `key` = ?', [$key]);
        if ($row && $row['hits'] > $limit) {
            header('Retry-After: ' . max(1, (int) $row['ttl']));
            throw new HttpError(429, $message);
        }
    }

    /**
     * Honeypot: на фронте в форме есть скрытое поле `website`. Люди его не заполняют, боты — заполняют.
     * Отвечаем «успехом», ничего не сохраняя.
     */
    public static function isBot(Request $req): bool
    {
        $body = $req->body();
        return is_array($body) && is_string($body['website'] ?? null) && trim($body['website']) !== '';
    }
}
