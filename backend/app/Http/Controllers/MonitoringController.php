<?php

namespace App\Http\Controllers;

use App\Services\Monitoring\MetricsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MonitoringController extends Controller
{
    protected MetricsService $metricsService;

    public function __construct(MetricsService $metricsService)
    {
        $this->metricsService = $metricsService;
    }

    /**
     * Get application metrics dashboard.
     */
    public function dashboard(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->metricsService->getDashboard(),
            'request_id' => request()->header('X-Request-ID'),
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Get health check status.
     */
    public function health(): JsonResponse
    {
        $health = $this->metricsService->getHealthCheck();
        
        return response()->json([
            'success' => $health['status'] === 'healthy',
            'data' => $health,
            'request_id' => request()->header('X-Request-ID'),
            'timestamp' => now()->toISOString(),
        ], $health['status'] === 'healthy' ? 200 : 503);
    }

    /**
     * Get specific metric statistics.
     */
    public function metrics(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'period' => 'nullable|integer|min:60|max:86400',
            'tags' => 'nullable|array',
            'tags.*' => 'string',
        ]);

        $period = $validated['period'] ?? 3600;
        $tags = $validated['tags'] ?? [];

        $stats = $this->metricsService->getStats($validated['name'], $tags, $period);

        return response()->json([
            'success' => true,
            'data' => [
                'metric' => $validated['name'],
                'period' => $period,
                'tags' => $tags,
                'statistics' => $stats,
            ],
            'request_id' => request()->header('X-Request-ID'),
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Get all current metrics.
     */
    public function allMetrics(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->metricsService->getAllMetrics(),
            'request_id' => request()->header('X-Request-ID'),
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Record custom metric (for testing/external systems).
     */
    public function recordMetric(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'value' => 'required|numeric',
            'type' => 'required|in:counter,gauge,timing',
            'tags' => 'nullable|array',
            'tags.*' => 'string',
        ]);

        $tags = $validated['tags'] ?? [];

        switch ($validated['type']) {
            case 'counter':
                $this->metricsService->increment($validated['name'], $tags, (float)$validated['value']);
                break;
            case 'gauge':
                $this->metricsService->gauge($validated['name'], (float)$validated['value'], $tags);
                break;
            case 'timing':
                $this->metricsService->timing($validated['name'], (float)$validated['value'], $tags);
                break;
        }

        return response()->json([
            'success' => true,
            'message' => 'Metric recorded successfully',
            'request_id' => request()->header('X-Request-ID'),
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Trigger system metrics collection.
     */
    public function collectSystemMetrics(): JsonResponse
    {
        $this->metricsService->recordSystemMetrics();
        $this->metricsService->recordBusinessMetrics();

        return response()->json([
            'success' => true,
            'message' => 'System metrics collected successfully',
            'request_id' => request()->header('X-Request-ID'),
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Clean up old metrics data.
     */
    public function cleanup(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'retention_period' => 'nullable|integer|min:3600|max:2592000',
        ]);

        $retentionPeriod = $validated['retention_period'] ?? 86400; // 24 hours default

        $this->metricsService->cleanup($retentionPeriod);

        return response()->json([
            'success' => true,
            'message' => 'Metrics cleanup completed',
            'retention_period' => $retentionPeriod,
            'request_id' => request()->header('X-Request-ID'),
            'timestamp' => now()->toISOString(),
        ]);
    }
}
