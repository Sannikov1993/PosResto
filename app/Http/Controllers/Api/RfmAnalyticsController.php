<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Services\RFMAnalysisService;

class RfmAnalyticsController extends Controller
{
    use Traits\ResolvesRestaurantId;

    // ==========================================
    // RFM-АНАЛИЗ КЛИЕНТОВ
    // ==========================================

    public function rfmAnalysis(Request $request): JsonResponse
    {
        $restaurantId = $this->getRestaurantId($request);
        $period = $request->input("period", 90);

        $service = new RFMAnalysisService();
        $data = $service->analyze($restaurantId, $period);

        return response()->json([
            "success" => true,
            "data" => $data,
        ]);
    }

    public function rfmSegments(Request $request): JsonResponse
    {
        $restaurantId = $this->getRestaurantId($request);
        $period = $request->input("period", 90);

        $service = new RFMAnalysisService();
        $data = $service->getSegmentsSummary($restaurantId, $period);

        return response()->json([
            "success" => true,
            "data" => $data,
        ]);
    }

    public function rfmSegmentDescriptions(): JsonResponse
    {
        $service = new RFMAnalysisService();
        return response()->json([
            "success" => true,
            "data" => $service->getSegmentDescriptions(),
        ]);
    }

    public function customerRfm(Request $request, int $customerId): JsonResponse
    {
        $restaurantId = $this->getRestaurantId($request);
        $period = $request->input("period", 90);

        $service = new RFMAnalysisService();
        $data = $service->getCustomerRFM($customerId, $restaurantId, $period);

        if (!$data) {
            return response()->json([
                "success" => false,
                "message" => "Клиент не найден или недоступен",
            ], 404);
        }

        return response()->json([
            "success" => true,
            "data" => $data,
        ]);
    }
}
