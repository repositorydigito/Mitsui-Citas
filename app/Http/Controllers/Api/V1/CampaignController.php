<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Campana;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

/**
 * Controller para campañas
 */
class CampaignController extends Controller
{
    /**
     * Listar campañas activas
     *
     * GET /api/v1/campaigns
     *
     * @return JsonResponse
     */
    public function index(): JsonResponse
    {
        try {
            $campaigns = Campana::where('status', 'active')
                ->where('end_date', '>=', now())
                ->orderBy('start_date', 'desc')
                ->get();

            Log::info('[API] Listando campañas', [
                'total' => $campaigns->count(),
            ]);

            return response()->json([
                'success' => true,
                'data' => $campaigns->map(function ($campaign) {
                    return [
                        'id' => $campaign->id,
                        'code' => $campaign->code,
                        'name' => $campaign->title,
                        'description' => $campaign->description,
                        'city' => $campaign->city,
                        'brand' => $campaign->brand,
                        'valid_from' => $campaign->start_date,
                        'valid_until' => $campaign->end_date,
                        'is_active' => $campaign->status === 'active',
                    ];
                }),
            ]);

        } catch (\Exception $e) {
            Log::error('[API] Error listando campañas', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Error interno del servidor',
            ], 500);
        }
    }
}
