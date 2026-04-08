<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;

class TestApiCommand extends Command
{
    protected $signature = 'api:test {--endpoint= : Test specific endpoint}';
    protected $description = 'Test API endpoints';

    public function handle(): int
    {
        $this->info('=== SaaSFoodTech API Testing ===');
        $baseUrl = 'http://localhost:8000/api';

        // Test database connection first
        $this->info('Testing database connection...');
        try {
            DB::select('SELECT 1');
            $this->info('Database: OK');
        } catch (\Exception $e) {
            $this->error('Database: FAILED - ' . $e->getMessage());
            return 1;
        }

        // Test 1: Health Check
        $this->info("\n1. Testing Health Check...");
        try {
            $response = Http::timeout(10)->get($baseUrl . '/monitoring/health');
            $this->info('Status: ' . $response->status());

            if ($response->successful()) {
                $this->info('Health Check: PASSED');
                $data = $response->json();
                if (isset($data['data']['status'])) {
                    $this->info('System Status: ' . $data['data']['status']);
                }
            } else {
                $this->error('Health Check: FAILED');
                $this->error('Response: ' . $response->body());
            }
        } catch (\Exception $e) {
            $this->error('Health Check: ERROR - ' . $e->getMessage());
        }

        // Test 2: Register User
        $this->info("\n2. Testing User Registration...");
        try {
            $userData = [
                'name' => 'Test User ' . time(),
                'email' => 'test' . time() . '@example.com',
                'password' => 'Password123!',
                'password_confirmation' => 'Password123!',
                'role' => 'customer'
            ];

            $response = Http::timeout(10)->post($baseUrl . '/register', $userData);
            $this->info('Status: ' . $response->status());

            if ($response->successful()) {
                $this->info('Registration: PASSED');
                $token = $response->json('access_token');
                $this->info('Token received: ' . substr($token, 0, 20) . '...');

                // Test 3: Get Current User
                $this->info("\n3. Testing Get Current User...");
                $userResponse = Http::timeout(10)->withToken($token)->get($baseUrl . '/me');
                $this->info('Status: ' . $userResponse->status());

                if ($userResponse->successful()) {
                    $this->info('Get User: PASSED');
                    $userData = $userResponse->json('data');
                    $this->info('User: ' . $userData['name'] . ' (' . $userData['email'] . ')');

                    // Test 4: Create Service Request
                    $this->info("\n4. Testing Service Request Creation...");
                    $requestData = [
                        'title' => 'Fix my kitchen sink',
                        'description' => 'The kitchen sink is leaking and needs professional repair.',
                        'latitude' => 40.7128,
                        'longitude' => -74.0060,
                        'category' => 'plumbing',
                        'urgency' => 'normal'
                    ];

                    $serviceResponse = Http::timeout(10)->withToken($token)->post($baseUrl . '/requests', $requestData);
                    $this->info('Status: ' . $serviceResponse->status());

                    if ($serviceResponse->successful()) {
                        $this->info('Service Request Creation: PASSED');
                        $serviceData = $serviceResponse->json('data');
                        $this->info('Request ID: ' . $serviceData['id']);
                        $this->info('Title: ' . $serviceData['title']);

                        // Test 5: List Service Requests
                        $this->info("\n5. Testing List Service Requests...");
                        $listResponse = Http::timeout(10)->withToken($token)->get($baseUrl . '/requests');
                        $this->info('Status: ' . $listResponse->status());

                        if ($listResponse->successful()) {
                            $this->info('List Requests: PASSED');
                            $requests = $listResponse->json('data');
                            $this->info('Total requests: ' . count($requests));
                        } else {
                            $this->error('List Requests: FAILED');
                        }
                    } else {
                        $this->error('Service Request Creation: FAILED');
                        $this->error('Response: ' . $serviceResponse->body());
                    }
                } else {
                    $this->error('Get User: FAILED');
                    $this->error('Response: ' . $userResponse->body());
                }
            } else {
                $this->error('Registration: FAILED');
                $this->error('Response: ' . $response->body());
            }
        } catch (\Exception $e) {
            $this->error('Registration: ERROR - ' . $e->getMessage());
        }

        // Test 6: Monitoring Dashboard
        $this->info("\n6. Testing Monitoring Dashboard...");
        try {
            $response = Http::timeout(10)->get($baseUrl . '/monitoring/dashboard');
            $this->info('Status: ' . $response->status());

            if ($response->successful()) {
                $this->info('Dashboard: PASSED');
                $data = $response->json('data');
                if (isset($data['overview'])) {
                    $this->info('System uptime: ' . ($data['overview']['uptime'] ?? 'N/A'));
                }
            } else {
                $this->error('Dashboard: FAILED');
            }
        } catch (\Exception $e) {
            $this->error('Dashboard: ERROR - ' . $e->getMessage());
        }

        $this->info("\n=== Testing Complete ===");
        $this->info('For interactive testing, open: http://localhost:8000/api/monitoring/health');
        $this->info('Use Postman with the collection in TESTING_GUIDE.md for advanced testing');

        return 0;
    }
}
