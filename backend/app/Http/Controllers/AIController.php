<?php

namespace App\Http\Controllers;

use App\Services\DescriptionEnhancerService;
use App\Services\OrderCategorizationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AIController extends Controller
{
    public function __construct(
        private readonly DescriptionEnhancerService $enhancer,
        private readonly OrderCategorizationService $categorizer
    ) {
    }

    /**
     * POST /api/ai/enhance
     * Enhance a service description with AI.
     *
     * Rate-limited to 10 requests/minute per user via route throttle middleware.
     * On AI failure Service returns original description (graceful degradation),
     * so this endpoint should never return a 5xx to the client.
     */
    public function enhance(Request $request): JsonResponse
    {
        $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:5000'],
        ]);

        // DescriptionEnhancerService handles all exceptions internally and falls back
        // to original description — no try/catch needed here.
        $enhanced = $this->enhancer->enhance(
            $request->title,
            $request->description
        );

        return response()->json([
            'enhanced_description' => $enhanced,
            'was_enhanced' => $enhanced !== $request->description,
        ]);
    }

    /**
     * POST /api/ai/categorize
     * Categorize a service request using AI.
     */
    public function categorize(Request $request): JsonResponse
    {
        $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:5000'],
        ]);

        $categorization = $this->categorizer->categorize(
            $request->title,
            $request->description
        );

        return response()->json([
            'success' => true,
            'data' => $categorization,
            'request_id' => $request->header('X-Request-ID'),
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * POST /api/ai/suggest-pricing
     * Suggest pricing for a service request using AI.
     */
    public function suggestPricing(Request $request): JsonResponse
    {
        $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:5000'],
            'category' => ['nullable', 'string', 'max:50'],
        ]);

        // First categorize if no category provided
        $category = $request->category;
        if (!$category) {
            $categorization = $this->categorizer->categorize(
                $request->title,
                $request->description
            );
            $category = $categorization['category'];
        }

        $pricing = $this->categorizer->suggestPricing(
            $request->title,
            $request->description,
            $category
        );

        return response()->json([
            'success' => true,
            'data' => array_merge($pricing, ['category' => $category]),
            'request_id' => $request->header('X-Request-ID'),
            'timestamp' => now()->toISOString(),
        ]);
    }
}
