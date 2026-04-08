<?php

namespace Tests\Unit;

use App\Services\GeolocationService;
use Tests\TestCase;

class GeolocationServiceTest extends TestCase
{
    /** @test */
    public function it_calculates_distance_correctly()
    {
        // Test distance between New York and Los Angeles
        $distance = GeolocationService::calculateDistance(40.7128, -74.0060, 34.0522, -118.2437);
        
        // Should be approximately 3935 km (with some tolerance for calculation method)
        $this->assertGreaterThan(3900, $distance);
        $this->assertLessThan(4000, $distance);
    }

    /** @test */
    public function it_validates_coordinates()
    {
        // Valid coordinates
        $result = GeolocationService::validateCoordinates(40.7128, -74.0060);
        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['errors']);

        // Invalid latitude
        $result = GeolocationService::validateCoordinates(91, -74.0060);
        $this->assertFalse($result['valid']);
        $this->assertContains('Latitude must be between -90 and 90 degrees', $result['errors']);

        // Invalid longitude
        $result = GeolocationService::validateCoordinates(40.7128, 181);
        $this->assertFalse($result['valid']);
        $this->assertContains('Longitude must be between -180 and 180 degrees', $result['errors']);
    }

    /** @test */
    public function it_converts_distances()
    {
        // Test km to miles
        $miles = GeolocationService::convertDistance(100, 'km', 'miles');
        $this->assertEqualsWithDelta(62.14, $miles, 0.1);

        // Test miles to km
        $km = GeolocationService::convertDistance(62.14, 'miles', 'km');
        $this->assertEqualsWithDelta(100, $km, 0.1);

        // Test km to meters
        $meters = GeolocationService::convertDistance(5, 'km', 'meters');
        $this->assertEquals(5000, $meters);
    }

    /** @test */
    public function it_estimates_travel_time()
    {
        $travelTime = GeolocationService::estimateTravelTime(100, 50); // 100km at 50km/h
        
        $this->assertEquals(2, $travelTime['hours']);
        $this->assertEquals(0, $travelTime['minutes']);
        $this->assertEquals(120, $travelTime['total_minutes']);
        $this->assertEquals('2h 0m', $travelTime['formatted']);
    }

    /** @test */
    public function it_gets_bounding_box()
    {
        $bbox = GeolocationService::getBoundingBox(40.7128, -74.0060, 50);
        
        $this->assertArrayHasKey('min_lat', $bbox);
        $this->assertArrayHasKey('max_lat', $bbox);
        $this->assertArrayHasKey('min_lon', $bbox);
        $this->assertArrayHasKey('max_lon', $bbox);
        
        $this->assertLessThan(40.7128, $bbox['min_lat']);
        $this->assertGreaterThan(40.7128, $bbox['max_lat']);
        $this->assertLessThan(-74.0060, $bbox['min_lon']);
        $this->assertGreaterThan(-74.0060, $bbox['max_lon']);
    }

    /** @test */
    public function it_checks_point_in_polygon()
    {
        // Simple square polygon around New York
        $polygon = [
            [40.8, -74.2],  // Top-left
            [40.8, -73.8],  // Top-right
            [40.6, -73.8],  // Bottom-right
            [40.6, -74.2],  // Bottom-left
        ];

        // Point inside polygon (New York coordinates)
        $inside = GeolocationService::isPointInPolygon(40.7128, -74.0060, $polygon);
        $this->assertTrue($inside);

        // Point outside polygon (Los Angeles coordinates)
        $outside = GeolocationService::isPointInPolygon(34.0522, -118.2437, $polygon);
        $this->assertFalse($outside);
    }
}
