<?php

namespace App\Http\Middleware;

use App\Models\AdminToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminTokenMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $plain = $request->bearerToken();
        if (! $plain) {
            return response()->json(['error' => 'Требуется авторизация'], 401);
        }

        $token = AdminToken::query()
            ->with('admin')
            ->where('token_hash', hash('sha256', $plain))
            ->where('expires_at', '>', now())
            ->first();

        if (! $token || ! $token->admin) {
            return response()->json(['error' => 'Токен недействителен или истёк'], 401);
        }

        $request->attributes->set('admin', $token->admin);
        $request->attributes->set('admin_token', $token);

        return $next($request);
    }
}
