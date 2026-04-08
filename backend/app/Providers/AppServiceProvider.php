<?php

namespace App\Providers;

use App\Models\ServiceRequest;
use App\Policies\ServiceRequestPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * The model → policy map.
     * Laravel auto-discovers policies for models in App\Models that follow
     * the naming convention, but explicit registration is clearer and safer.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        ServiceRequest::class => ServiceRequestPolicy::class,
    ];

    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->registerPolicies();

        // Polyfill MySQL-native spatial functions for SQLite (Testing/Local)
        if (\Illuminate\Support\Facades\DB::getDriverName() === 'sqlite') {
            $pdo = \Illuminate\Support\Facades\DB::connection()->getPdo();

            $pdo->sqliteCreateFunction('ST_Distance_Sphere', function ($p1, $p2) {
                // Parse POINT(lng lat) or similar
                $parse = fn($s) => preg_match('/POINT\s*\(\s*(-?\d+\.?\d*)\s+(-?\d+\.?\d*)\s*\)/i', $s, $m)
                    ? [(float) $m[1], (float) $m[2]] : null;

                $c1 = $parse($p1);
                $c2 = $parse($p2);

                if (!$c1 || !$c2)
                    return 0;

                $earthRadius = 6371000; // Meters
                $dLat = deg2rad($c2[1] - $c1[1]);
                $dLon = deg2rad($c2[0] - $c1[0]);

                $a = sin($dLat / 2) * sin($dLat / 2) +
                    cos(deg2rad($c1[1])) * cos(deg2rad($c2[1])) *
                    sin($dLon / 2) * sin($dLon / 2);

                return 2 * atan2(sqrt($a), sqrt(1 - $a)) * $earthRadius;
            }, 2);

            $pdo->sqliteCreateFunction('POINT', function ($lng, $lat) {
                return "POINT($lng $lat)";
            }, 2);
        }
    }
}
