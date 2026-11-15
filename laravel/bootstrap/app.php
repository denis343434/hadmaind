<?php

use App\Http\Middleware\AdminTokenMiddleware;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'admin.token' => AdminTokenMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $api = fn (Request $request): bool => $request->is('api/*');

        $exceptions->render(function (ValidationException $e, Request $request) use ($api) {
            if (! $api($request)) {
                return null;
            }

            $details = [];
            foreach ($e->errors() as $field => $messages) {
                $details[] = ['path' => $field, 'message' => $messages[0]];
            }

            return response()->json([
                'error' => 'Ошибка валидации',
                'details' => $details,
            ], 400);
        });

        $exceptions->render(function (AuthenticationException $e, Request $request) use ($api) {
            return $api($request)
                ? response()->json(['error' => 'Требуется авторизация'], 401)
                : null;
        });

        $exceptions->render(function (ThrottleRequestsException $e, Request $request) use ($api) {
            return $api($request)
                ? response()->json(['error' => 'Слишком много запросов, попробуйте позже'], 429)
                : null;
        });

        $exceptions->render(function (ModelNotFoundException|NotFoundHttpException $e, Request $request) use ($api) {
            return $api($request)
                ? response()->json(['error' => 'Запись не найдена'], 404)
                : null;
        });

        $exceptions->render(function (QueryException $e, Request $request) use ($api) {
            if (! $api($request)) {
                return null;
            }

            $code = (int) ($e->errorInfo[1] ?? 0);
            if (in_array($code, [1062, 1451, 1452, 1213, 1205], true)) {
                return response()->json([
                    'error' => $code === 1062 ? 'Запись с такими данными уже существует' : 'Конфликт связанных записей',
                ], 409);
            }

            return null;
        });

        $exceptions->render(function (Throwable $e, Request $request) use ($api) {
            if (! $api($request) || $e instanceof HttpExceptionInterface) {
                return null;
            }

            Log::error($e::class.': '.$e->getMessage(), ['exception' => $e]);

            $body = ['error' => 'Внутренняя ошибка сервера'];
            if (config('app.debug')) {
                $body['message'] = $e->getMessage();
            }

            return response()->json($body, 500);
        });
    })->create();
