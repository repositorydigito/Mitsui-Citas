<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * Controller para autenticación API del agente IA
 */
class AuthController extends Controller
{
    /**
     * Generar token de autenticación para el agente IA
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function generateToken(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'client_id' => 'required|string',
            'client_secret' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Datos de autenticación inválidos',
                'details' => $validator->errors()
            ], 400);
        }

        // Validar credenciales contra configuración
        $validClientId = config('api.client_id', 'agent-ia-mitsui');
        $validClientSecret = config('api.client_secret', 'change-in-production');

        if ($request->client_id !== $validClientId ||
            $request->client_secret !== $validClientSecret) {

            Log::warning('[API Auth] Intento de autenticación fallido', [
                'client_id' => $request->client_id,
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Credenciales inválidas',
                'message' => 'El client_id o client_secret son incorrectos'
            ], 401);
        }

        // Retornar el token configurado
        $token = config('api.agent_token');

        Log::info('[API Auth] Token generado exitosamente', [
            'client_id' => $request->client_id,
            'ip' => $request->ip(),
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'access_token' => $token,
                'token_type' => 'Bearer',
                'expires_in' => null, // Token no expira
            ],
            'message' => 'Token generado exitosamente'
        ]);
    }
}
