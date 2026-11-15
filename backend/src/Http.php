<?php
declare(strict_types=1);

namespace App;

final class HttpError extends \RuntimeException
{
    public function __construct(public readonly int $status, string $message, public readonly mixed $details = null)
    {
        parent::__construct($message);
    }

    public static function badRequest(string $msg = 'Некорректный запрос', mixed $details = null): self
    {
        return new self(400, $msg, $details);
    }

    public static function unauthorized(string $msg = 'Требуется авторизация'): self
    {
        return new self(401, $msg);
    }

    public static function notFound(string $msg = 'Не найдено'): self
    {
        return new self(404, $msg);
    }

    public static function conflict(string $msg = 'Конфликт', mixed $details = null): self
    {
        return new self(409, $msg, $details);
    }
}

final class Request
{
    /** @var array<string, string> параметры пути (:id, :code) */
    public array $params = [];
    /** Админ из JWT после requireAdmin: ['sub' => id, 'email' => ...] */
    public ?array $admin = null;
    private mixed $body = null;
    private bool $bodyRead = false;

    public function __construct(
        public readonly string $method,
        public readonly string $path,
        /** @var array<string, mixed> */
        public readonly array $query,
    ) {
    }

    /** Путь берётся после сегмента /api: сайт может лежать и в подпапке. */
    public static function fromGlobals(): self
    {
        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $path = preg_replace('#^.*?/api(?=/|$)#', '', $uri, 1) ?? '';
        $path = '/' . trim($path, '/');
        return new self(strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET'), $path, $_GET);
    }

    /** JSON-тело запроса (объект → массив). Пустое тело → []. */
    public function body(): mixed
    {
        if (!$this->bodyRead) {
            $this->bodyRead = true;
            $raw = file_get_contents('php://input') ?: '';
            if (trim($raw) === '') {
                $this->body = [];
            } else {
                try {
                    $this->body = json_decode($raw, true, 64, JSON_THROW_ON_ERROR);
                } catch (\JsonException) {
                    throw HttpError::badRequest('Некорректный JSON');
                }
            }
        }
        return $this->body;
    }

    public function param(string $name): string
    {
        return $this->params[$name] ?? '';
    }

    /** Положительный целый :id из пути. */
    public function id(string $name = 'id'): int
    {
        $raw = $this->param($name);
        if (!preg_match('/^[1-9]\d{0,9}$/', $raw)) throw HttpError::badRequest("Некорректный $name");
        return (int) $raw;
    }

    public function header(string $name): string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        $value = $_SERVER[$key] ?? $_SERVER["REDIRECT_$key"] ?? '';
        if ($value === '' && strcasecmp($name, 'Authorization') === 0 && function_exists('getallheaders')) {
            // Apache + CGI/FPM без SetEnvIf в .htaccess
            foreach (getallheaders() as $k => $v) if (strcasecmp($k, 'Authorization') === 0) $value = $v;
        }
        return (string) $value;
    }

    public function ip(): string
    {
        if (env('TRUST_PROXY')) {
            $forwarded = trim(explode(',', $this->header('X-Forwarded-For'))[0] ?? '');
            if ($forwarded !== '') return $forwarded;
        }
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
}

final class Response
{
    public function __construct(public readonly mixed $data = null, public readonly int $status = 200)
    {
    }

    public static function created(mixed $data): self
    {
        return new self($data, 201);
    }

    public static function noContent(): self
    {
        return new self(null, 204);
    }

    public function send(): void
    {
        http_response_code($this->status);
        if ($this->status === 204) return;
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($this->data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    }
}

final class Router
{
    /** @var list<array{string, string, callable, list<callable>}> */
    private array $routes = [];
    /** @var list<callable> общие middleware для маршрутов, добавляемых внутри group() */
    private array $stack = [];
    private string $prefix = '';

    public function get(string $path, callable $handler, callable ...$mw): void
    {
        $this->add('GET', $path, $handler, $mw);
    }

    public function post(string $path, callable $handler, callable ...$mw): void
    {
        $this->add('POST', $path, $handler, $mw);
    }

    public function put(string $path, callable $handler, callable ...$mw): void
    {
        $this->add('PUT', $path, $handler, $mw);
    }

    public function patch(string $path, callable $handler, callable ...$mw): void
    {
        $this->add('PATCH', $path, $handler, $mw);
    }

    public function delete(string $path, callable $handler, callable ...$mw): void
    {
        $this->add('DELETE', $path, $handler, $mw);
    }

    /** Маршруты с общим префиксом и middleware: group('/admin', fn, requireAdmin). */
    public function group(string $prefix, callable $register, callable ...$mw): void
    {
        [$prevPrefix, $prevStack] = [$this->prefix, $this->stack];
        $this->prefix .= $prefix;
        array_push($this->stack, ...$mw);
        $register($this);
        [$this->prefix, $this->stack] = [$prevPrefix, $prevStack];
    }

    private function add(string $method, string $path, callable $handler, array $mw): void
    {
        $full = rtrim($this->prefix . $path, '/') ?: '/';
        $regex = '#^' . preg_replace('#:([a-zA-Z]+)#', '(?P<$1>[^/]+)', $full) . '$#';
        $this->routes[] = [$method, $regex, $handler, [...$this->stack, ...$mw]];
    }

    public function dispatch(Request $req): mixed
    {
        foreach ($this->routes as [$method, $regex, $handler, $mw]) {
            if ($method !== $req->method || !preg_match($regex, $req->path, $m)) continue;
            $req->params = array_map('rawurldecode', array_filter($m, 'is_string', ARRAY_FILTER_USE_KEY));
            foreach ($mw as $fn) $fn($req);
            return $handler($req);
        }
        throw HttpError::notFound('Маршрут не найден');
    }
}

/** Задачи после отправки ответа клиенту (уведомления): клиент не ждёт Telegram и почту. */
final class Defer
{
    /** @var list<callable> */
    private static array $tasks = [];

    public static function add(callable $task): void
    {
        self::$tasks[] = $task;
    }

    public static function run(): void
    {
        if (!self::$tasks) return;
        if (function_exists('fastcgi_finish_request')) fastcgi_finish_request();
        elseif (function_exists('litespeed_finish_request')) litespeed_finish_request();
        ignore_user_abort(true);
        foreach (self::$tasks as $task) {
            try {
                $task();
            } catch (\Throwable $e) {
                error_log('Отложенная задача: ' . $e->getMessage());
            }
        }
        self::$tasks = [];
    }
}

/** Пагинированный ответ списка. */
function paged(array $items, int $total, int $page, int $limit): array
{
    return ['items' => $items, 'total' => $total, 'page' => $page, 'pages' => (int) ceil($total / $limit)];
}
