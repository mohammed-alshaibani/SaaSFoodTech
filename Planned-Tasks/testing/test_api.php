<?php

/**
 * Simple API Test Script
 * Run this script to test your backend API endpoints
 */

require_once __DIR__ . '/vendor/autoload.php';

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

$baseUrl = 'http://localhost:8000/api';

echo "=== Otex API Testing Script ===\n\n";

// Test 1: Health Check
echo "1. Testing Health Check...\n";
try {
    $response = Http::get($baseUrl . '/monitoring/health');
    echo "Status: " . $response->status() . "\n";
    if ($response->successful()) {
        echo "Response: " . json_encode($response->json(), JSON_PRETTY_PRINT) . "\n";
        echo "Health Check: PASSED\n\n";
    } else {
        echo "Health Check: FAILED\n\n";
    }
} catch (Exception $e) {
    echo "Health Check: ERROR - " . $e->getMessage() . "\n\n";
}

// Test 2: Register User
echo "2. Testing User Registration...\n";
try {
    $userData = [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
        'role' => 'customer'
    ];
    
    $response = Http::post($baseUrl . '/register', $userData);
    echo "Status: " . $response->status() . "\n";
    echo "Response: " . json_encode($response->json(), JSON_PRETTY_PRINT) . "\n";
    
    if ($response->successful()) {
        echo "Registration: PASSED\n";
        $token = $response->json('access_token');
        echo "Token received: " . substr($token, 0, 20) . "...\n\n";
    } else {
        echo "Registration: FAILED\n\n";
    }
} catch (Exception $e) {
    echo "Registration: ERROR - " . $e->getMessage() . "\n\n";
}

// Test 3: Login User
echo "3. Testing User Login...\n";
try {
    $loginData = [
        'email' => 'test@example.com',
        'password' => 'Password123!'
    ];
    
    $response = Http::post($baseUrl . '/login', $loginData);
    echo "Status: " . $response->status() . "\n";
    echo "Response: " . json_encode($response->json(), JSON_PRETTY_PRINT) . "\n";
    
    if ($response->successful()) {
        echo "Login: PASSED\n";
        $token = $response->json('access_token');
        echo "Token: " . substr($token, 0, 20) . "...\n\n";
    } else {
        echo "Login: FAILED\n\n";
    }
} catch (Exception $e) {
    echo "Login: ERROR - " . $e->getMessage() . "\n\n";
}

// Test 4: Create Service Request (with token)
echo "4. Testing Service Request Creation...\n";
try {
    if (isset($token)) {
        $requestData = [
            'title' => 'Fix my kitchen sink',
            'description' => 'The kitchen sink is leaking and needs professional repair.',
            'latitude' => 40.7128,
            'longitude' => -74.0060,
            'category' => 'plumbing',
            'urgency' => 'normal'
        ];
        
        $response = Http::withToken($token)->post($baseUrl . '/requests', $requestData);
        echo "Status: " . $response->status() . "\n";
        echo "Response: " . json_encode($response->json(), JSON_PRETTY_PRINT) . "\n";
        
        if ($response->successful()) {
            echo "Service Request Creation: PASSED\n\n";
        } else {
            echo "Service Request Creation: FAILED\n\n";
        }
    } else {
        echo "No token available, skipping service request test\n\n";
    }
} catch (Exception $e) {
    echo "Service Request Creation: ERROR - " . $e->getMessage() . "\n\n";
}

echo "=== Testing Complete ===\n";
echo "Open your browser and navigate to: http://localhost:8000/api/monitoring/health\n";
echo "For interactive testing, use Postman or the TESTING_GUIDE.md file\n";
