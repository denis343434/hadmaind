<?php
declare(strict_types=1);

namespace App;

/**
 * Сборка API. Подключается из site/api/index.php (хостинг) или backend/router.php (локально).
 * Модули включаются пресетом SITE_PRESET или явно: MODULES=pages,leads,menu.
 * Новый модуль = файл modules/<name>.php (return ['public' => fn, 'admin' => fn]) + имя в ALL_MODULES.
 */

const BACKEND_DIR = __DIR__;
const ALL_MODULES = ['pages', 'leads', 'media', 'menu', 'booking', 'shop'];
const PRESETS = [
    'landing' => ['pages', 'leads', 'media'],
    'cafe' => ['pages', 'leads', 'media', 'menu', 'booking'], // booking — бронь столов
    'entertainment' => ['pages', 'leads', 'media', 'booking'],
    'shop' => ['leads', 'media', 'shop'], // интернет-магазин: каталог и заказы
];

require __DIR__ . '/src/Env.php';
require __DIR__ . '/src/Http.php';
require __DIR__ . '/src/Db.php';
require __DIR__ . '/src/Validator.php';
require __DIR__ . '/src/Auth.php';
require __DIR__ . '/src/Notify.php';
require __DIR__ . '/src/Slots.php';
require __DIR__ . '/src/Booking.php';

Env::load(__DIR__ . '/.env');
date_default_timezone_set('UTC');

/** @return list<string> */
function enabledModules(): array
{
    $explicit = env('MODULES');
    if (!$explicit) return PRESETS[env('SITE_PRESET')] ?? PRESETS['landing'];
    $unknown = array_diff($explicit, ALL_MODULES);
    if ($unknown) throw new \RuntimeException('Неизвестные модули в MODULES: ' . implode(', ', $unknown));
    return array_values($explicit);
}

function buildRouter(): Router
{
    $load = fn (string $name) => require __DIR__ . "/modules/$name.php";
    $modules = ['settings' => $load('settings'), 'auth' => $load('auth')];
    foreach (enabledModules() as $name) $modules[$name] = $load($name);

    $router = new Router();
    $router->get('/health', function () {
        Db::value('SELECT 1');
        return ['ok' => true, 'preset' => env('SITE_PRESET'), 'modules' => enabledModules()];
    });
    foreach ($modules as $m) if (isset($m['public'])) $m['public']($router);

    // /admin/auth/* сами решают, где нужен токен (login — без него)
    $router->group('/admin', $modules['auth']['admin']);
    $router->group('/admin', function (Router $r) use ($modules) {
        foreach ($modules as $name => $m) if ($name !== 'auth' && isset($m['admin'])) $m['admin']($r);
    }, [Auth::class, 'requireAdmin']);
    return $router;
}

function sendCors(): void
{
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if ($origin === '') return;
    $allowed = env('CORS_ORIGINS');
    if (in_array($origin, $allowed, true) || (!$allowed && Env::isDev())) {
        header("Access-Control-Allow-Origin: $origin");
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Allow-Headers: Content-Type, Authorization');
        header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
        header('Vary: Origin');
    }
}

/** Обработать текущий HTTP-запрос к /api. */
function handleRequest(): void
{
    ini_set('display_errors', '0');
    set_error_handler(function (int $severity, string $message, string $file, int $line) {
        if (!(error_reporting() & $severity)) return false;
        throw new \ErrorException($message, 0, $severity, $file, $line);
    });

    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: no-store');
    sendCors();
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
        http_response_code(204);
        return;
    }

    try {
        $req = Request::fromGlobals();
        $result = buildRouter()->dispatch($req);
        $response = $result instanceof Response ? $result : new Response($result);
    } catch (\Throwable $e) {
        $response = errorResponse($e);
    }
    $response->send();
    Defer::run();
}

function errorResponse(\Throwable $e): Response
{
    if ($e instanceof HttpError) {
        $body = ['error' => $e->getMessage()];
        if ($e->details !== null) $body['details'] = $e->details;
        return new Response($body, $e->status);
    }
    if ($e instanceof \PDOException) {
        $code = (int) ($e->errorInfo[1] ?? 0);
        if ($code === 1062) return new Response(['error' => 'Запись с такими данными уже существует'], 409);
        if ($code === 1451 || $code === 1452) return new Response(['error' => 'Есть связанные записи'], 409);
        if ($code === 1213 || $code === 1205) return new Response(['error' => 'Конфликт параллельных запросов, повторите попытку'], 409);
    }
    error_log(sprintf('[api] %s: %s in %s:%d', $e::class, $e->getMessage(), $e->getFile(), $e->getLine()));
    $body = ['error' => 'Внутренняя ошибка сервера'];
    if (Env::isDev()) $body['message'] = $e->getMessage();
    return new Response($body, 500);
}
