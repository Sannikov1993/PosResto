<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Services\ChurnAnalysisService;

class ChurnAnalyticsController extends Controller
{
    use Traits\ResolvesRestaurantId;

    // ==========================================
    // АНАЛИЗ ОТТОКА КЛИЕНТОВ
    // ==========================================

    public function churnAnalysis(Request $request): JsonResponse
    {
        $restaurantId = $this->getRestaurantId($request);
        $lookbackDays = $request->input("lookback", 180);

        $service = new ChurnAnalysisService();
        $data = $service->analyze($restaurantId, $lookbackDays);

        return response()->json([
            "success" => true,
            "data" => $data,
        ]);
    }

    public function churnAlerts(Request $request): JsonResponse
    {
        $restaurantId = $this->getRestaurantId($request);

        $service = new ChurnAnalysisService();
        $data = $service->getAlerts($restaurantId);

        return response()->json([
            "success" => true,
            "data" => $data,
        ]);
    }

    public function churnTrend(Request $request): JsonResponse
    {
        $restaurantId = $this->getRestaurantId($request);
        $months = $request->input("months", 6);

        $service = new ChurnAnalysisService();
        $data = $service->calculateChurnTrend($restaurantId, $months);

        return response()->json([
            "success" => true,
            "data" => $data,
        ]);
    }
}
