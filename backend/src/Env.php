<?php
declare(strict_types=1);

namespace App;

/**
 * Настройки из backend/.env. Тип значения берётся из значения по умолчанию:
 * int → число, bool → true/false/1/0, array → список через запятую.
 * Переменные окружения сервера (getenv) перекрывают файл.
 */
final class Env
{
    private const DEFAULTS = [
        'APP_ENV' => 'production',
        'PUBLIC_URL' => '',
        'CORS_ORIGINS' => [],
        'TRUST_PROXY' => false,

        'SITE_PRESET' => 'landing',
        'MODULES' => [],

        'DB_HOST' => 'localhost',
        'DB_PORT' => 3306,
        'DB_NAME' => '',
        'DB_USER' => '',
        'DB_PASS' => '',

        'JWT_SECRET' => '',
        'JWT_EXPIRES_IN' => '7d',
        'ADMIN_EMAIL' => '',
        'ADMIN_PASSWORD' => '',

        'TIMEZONE' => 'Europe/Moscow',
        'BOOKING_SLOT_STEP_MIN' => 30,
        'BOOKING_MIN_LEAD_MIN' => 60,
        'BOOKING_MAX_DAYS_AHEAD' => 60,
        'BOOKING_AUTO_CONFIRM' => false,
        'BOOKING_CANCEL_MIN_HOURS' => 3,

        'TELEGRAM_BOT_TOKEN' => '',
        'TELEGRAM_CHAT_ID' => [],

        'SMTP_HOST' => '',
        'SMTP_PORT' => 465,
        'SMTP_SECURE' => true,
        'SMTP_USER' => '',
        'SMTP_PASS' => '',
        'MAIL_FROM' => 'Сайт <noreply@example.com>',
        'MAIL_ADMIN_TO' => [],

        'UPLOAD_DIR' => '../uploads',
        'UPLOAD_MAX_MB' => 10,
    ];

    /** @var array<string, string> */
    private static array $file = [];
    /** @var array<string, mixed> */
    private static array $cache = [];

    public static function load(string $path): void
    {
        if (!is_file($path)) return;
        foreach (file($path, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) continue;
            [$key, $value] = array_map('trim', explode('=', $line, 2));
            $quote = $value[0] ?? '';
            if (($quote === '"' || $quote === "'") && strlen($value) >= 2 && str_ends_with($value, $quote)) {
                $value = substr($value, 1, -1);
            } else {
                $value = trim(preg_replace('/\s+#.*$/', '', $value) ?? $value); // комментарий в конце строки
            }
            self::$file[$key] = $value;
        }
    }

    public static function get(string $key): mixed
    {
        if (array_key_exists($key, self::$cache)) return self::$cache[$key];
        if (!array_key_exists($key, self::DEFAULTS)) throw new \LogicException("Неизвестная настройка $key");

        $default = self::DEFAULTS[$key];
        $raw = getenv($key);
        if ($raw === false) $raw = self::$file[$key] ?? '';
        $raw = trim($raw);

        $value = match (true) {
            $raw === '' => $default,
            is_bool($default) => in_array(strtolower($raw), ['true', '1', 'yes', 'on'], true),
            is_int($default) => is_numeric($raw) ? (int) $raw : $default,
            is_array($default) => array_values(array_filter(array_map('trim', explode(',', $raw)), 'strlen')),
            default => $raw,
        };
        return self::$cache[$key] = $value;
    }

    public static function isDev(): bool
    {
        return self::get('APP_ENV') === 'development';
    }
}

/** Короткий доступ к настройкам: env('TIMEZONE'). */
function env(string $key): mixed
{
    return Env::get($key);
}
