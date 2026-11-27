<?php

namespace App\Http\Middleware;

use App\Models\ApiToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A23 · Доступ к /api/v1 по токену: Authorization: Bearer banq_… (или X-Api-Token).
 * Параметр — нужное право: api.token:read | api.token:write.
 */
class AuthenticateApiToken
{
    public function handle(Request $request, Closure $next, string $ability = 'read'): Response
    {
        $token = ApiToken::findPlain($request->bearerToken() ?: $request->header('X-Api-Token'));
        if (! $token) {
            return response()->json(['message' => 'Нужен токен API: Authorization: Bearer <token>. Создать — /admin/exchange/tokens'], 401);
        }
        if (! $token->can($ability)) {
            return response()->json(['message' => "У токена нет права «{$ability}»"], 403);
        }

        $token->forceFill(['last_used_at' => now(), 'last_ip' => $request->ip()])->saveQuietly();
        $request->attributes->set('api_token', $token);

        return $next($request);
    }
}
