<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\C4C\AvailabilityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Controller para health check de la API
 */
class HealthController extends Controller
{
    /**
     * Verificar estado del sistema y servicios
     *
     * @return JsonResponse
     */
    public function check(): JsonResponse
    {
        $status = 'healthy';
        $services = [];

        // 1. Verificar base de datos
        try {
            DB::connection()->getPdo();
            $services['database'] = 'up';
        } catch (\Exception $e) {
            $services['database'] = 'down';
            $status = 'degraded';
            Log::error('[Health Check] Database connection failed', ['error' => $e->getMessage()]);
        }

        // 2. Verificar C4C Availability Service
        try {
            $availabilityService = app(AvailabilityService::class);
            $healthCheck = $availabilityService->healthCheck();
            $services['c4c_availability'] = $healthCheck['success'] ? 'up' : 'down';

            if (!$healthCheck['success']) {
                $status = 'degraded';
            }
        } catch (\Exception $e) {
            $services['c4c_availability'] = 'down';
            $status = 'degraded';
            Log::error('[Health Check] C4C Availability service failed', ['error' => $e->getMessage()]);
        }

        // 3. Verificar sistema de queue
        try {
            $queueConnection = config('queue.default');
            $services['queue'] = $queueConnection ? 'configured' : 'not_configured';
        } catch (\Exception $e) {
            $services['queue'] = 'error';
            Log::error('[Health Check] Queue check failed', ['error' => $e->getMessage()]);
        }

        // 4. Verificar storage
        try {
            $storagePath = storage_path();
            $services['storage'] = is_writable($storagePath) ? 'writable' : 'read_only';
        } catch (\Exception $e) {
            $services['storage'] = 'error';
            Log::error('[Health Check] Storage check failed', ['error' => $e->getMessage()]);
        }

        $httpStatus = $status === 'healthy' ? 200 : ($status === 'degraded' ? 503 : 500);

        return response()->json([
            'status' => $status,
            'timestamp' => now()->toIso8601String(),
            'services' => $services,
            'version' => config('app.version', '1.0.0'),
        ], $httpStatus);
    }
}
