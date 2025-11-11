<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware para autenticar requests API con Bearer token
 */
class AuthApiToken
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (!$token) {
            Log::warning('[API] Request sin token de autenticación', [
                'ip' => $request->ip(),
                'path' => $request->path(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Token de autenticación requerido',
                'message' => 'Debe incluir un Bearer token en el header Authorization'
            ], 401);
        }

        // Validar el token contra la configuración
        $validToken = config('api.agent_token');

        if ($token !== $validToken) {
            Log::warning('[API] Token inválido', [
                'ip' => $request->ip(),
                'path' => $request->path(),
                'token_prefix' => substr($token, 0, 10) . '...'
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Token inválido',
                'message' => 'El token proporcionado no es válido'
            ], 401);
        }

        Log::info('[API] Request autenticado exitosamente', [
            'ip' => $request->ip(),
            'path' => $request->path(),
        ]);

        return $next($request);
    }
}
