# Backend Testing Guide

## Quick Start Testing

### 1. Run Built-in Tests
```bash
# Navigate to backend directory
cd "c:\Users\User\Desktop\e-store\Otex App2\OtexApp-v2\FullAppApiOtex\SAAS FullStack\backend"

# Install dependencies (if not already done)
composer install

# Run all tests
php artisan test

# Run specific test file
php artisan test tests/Feature/ServiceRequestTest.php

# Run with coverage
php artisan test --coverage

# Run tests with verbose output
php artisan test --verbose
```

### 2. Start Development Server
```bash
# Start Laravel development server
php artisan serve

# Server will be available at: http://localhost:8000
# API endpoints at: http://localhost:8000/api
```

## Manual API Testing

### Using curl Commands

#### 1. Register a New User
```bash
curl -X POST http://localhost:8000/api/register \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "name": "John Doe",
    "email": "john@example.com",
    "password": "Password123!",
    "password_confirmation": "Password123!",
    "role": "customer"
  }'
```

#### 2. Login User
```bash
curl -X POST http://localhost:8000/api/login \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "email": "john@example.com",
    "password": "Password123!"
  }'
```

#### 3. Create Service Request (replace TOKEN with actual token)
```bash
curl -X POST http://localhost:8000/api/requests \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  -d '{
    "title": "Fix my kitchen sink",
    "description": "The kitchen sink is leaking and needs professional repair.",
    "latitude": 40.7128,
    "longitude": -74.0060,
    "category": "plumbing",
    "urgency": "normal"
  }'
```

#### 4. List Service Requests
```bash
curl -X GET http://localhost:8000/api/requests \
  -H "Accept: application/json" \
  -H "Authorization: Bearer YOUR_JWT_TOKEN"
```

#### 5. Upload File
```bash
curl -X POST http://localhost:8000/api/attachments/upload \
  -H "Accept: application/json" \
  -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  -F "file=@/path/to/your/image.jpg" \
  -F "service_request_id=1"
```

#### 6. Check Health
```bash
curl -X GET http://localhost:8000/api/monitoring/health \
  -H "Accept: application/json"
```

## Testing with Postman

### 1. Import Postman Collection

Create a new file `otex-api.postman_collection.json`:

```json
{
  "info": {
    "name": "Otex API",
    "description": "API testing collection for Otex Service Platform",
    "schema": "https://schema.getpostman.com/json/collection/v2.1.0/collection.json"
  },
  "variable": [
    {
      "key": "baseUrl",
      "value": "http://localhost:8000/api"
    },
    {
      "key": "token",
      "value": ""
    }
  ],
  "item": [
    {
      "name": "Authentication",
      "item": [
        {
          "name": "Register",
          "request": {
            "method": "POST",
            "header": [
              {
                "key": "Content-Type",
                "value": "application/json"
              }
            ],
            "body": {
              "mode": "raw",
              "raw": "{\n  \"name\": \"John Doe\",\n  \"email\": \"john@example.com\",\n  \"password\": \"Password123!\",\n  \"password_confirmation\": \"Password123!\",\n  \"role\": \"customer\"\n}"
            },
            "url": {
              "raw": "{{baseUrl}}/register",
              "host": ["{{baseUrl}}"],
              "path": ["register"]
            }
          }
        },
        {
          "name": "Login",
          "request": {
            "method": "POST",
            "header": [
              {
                "key": "Content-Type",
                "value": "application/json"
              }
            ],
            "body": {
              "mode": "raw",
              "raw": "{\n  \"email\": \"john@example.com\",\n  \"password\": \"Password123!\"\n}"
            },
            "url": {
              "raw": "{{baseUrl}}/login",
              "host": ["{{baseUrl}}"],
              "path": ["login"]
            },
            "event": [
              {
                "listen": "test",
                "script": {
                  "exec": [
                    "if (pm.response.code === 200) {",
                    "    const response = pm.response.json();",
                    "    pm.collectionVariables.set('token', response.access_token);",
                    "}"
                  ]
                }
              }
            ]
          }
        }
      ]
    },
    {
      "name": "Service Requests",
      "item": [
        {
          "name": "Create Request",
          "request": {
            "method": "POST",
            "header": [
              {
                "key": "Content-Type",
                "value": "application/json"
              },
              {
                "key": "Authorization",
                "value": "Bearer {{token}}"
              }
            ],
            "body": {
              "mode": "raw",
              "raw": "{\n  \"title\": \"Fix my kitchen sink\",\n  \"description\": \"The kitchen sink is leaking and needs professional repair.\",\n  \"latitude\": 40.7128,\n  \"longitude\": -74.0060,\n  \"category\": \"plumbing\",\n  \"urgency\": \"normal\"\n}"
            },
            "url": {
              "raw": "{{baseUrl}}/requests",
              "host": ["{{baseUrl}}"],
              "path": ["requests"]
            }
          }
        },
        {
          "name": "List Requests",
          "request": {
            "method": "GET",
            "header": [
              {
                "key": "Authorization",
                "value": "Bearer {{token}}"
              }
            ],
            "url": {
              "raw": "{{baseUrl}}/requests",
              "host": ["{{baseUrl}}"],
              "path": ["requests"]
            }
          }
        }
      ]
    },
    {
      "name": "Monitoring",
      "item": [
        {
          "name": "Health Check",
          "request": {
            "method": "GET",
            "url": {
              "raw": "{{baseUrl}}/monitoring/health",
              "host": ["{{baseUrl}}"],
              "path": ["monitoring", "health"]
            }
          }
        },
        {
          "name": "Dashboard",
          "request": {
            "method": "GET",
            "header": [
              {
                "key": "Authorization",
                "value": "Bearer {{token}}"
              }
            ],
            "url": {
              "raw": "{{baseUrl}}/monitoring/dashboard",
              "host": ["{{baseUrl}}"],
              "path": ["monitoring", "dashboard"]
            }
          }
        }
      ]
    }
  ]
}
```

### 2. Import to Postman
1. Open Postman
2. Click "Import" 
3. Select the JSON file
4. Update the `baseUrl` variable if needed
5. Run the "Register" request first
6. Run the "Login" request (it will automatically save the token)
7. Run other requests with the saved token

## Testing with Browser

### 1. Open API Documentation
```bash
# If you have Swagger UI installed
http://localhost:8000/api/documentation

# Or view the markdown docs
# Open API_DOCUMENTATION.md in your browser
```

### 2. Test with Browser DevTools
1. Open browser DevTools (F12)
2. Go to Network tab
3. Make API calls using fetch in Console:

```javascript
// Test login
fetch('http://localhost:8000/api/login', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json'
  },
  body: JSON.stringify({
    email: 'john@example.com',
    password: 'Password123!'
  })
})
.then(response => response.json())
.then(data => console.log(data));
```

## Database Testing

### 1. Setup Test Database
```bash
# Create test database
mysql -u root -p
CREATE DATABASE otex_test;

# Update .env for testing
cp .env .env.testing
# Edit .env.testing to use test database
```

### 2. Run Database Migrations
```bash
# Fresh migration with seeding
php artisan migrate:fresh --seed

# Run specific migrations
php artisan migrate --path=database/migrations/2023_12_01_create_users_table.php
```

### 3. Test Database Connections
```bash
# Check database connection
php artisan tinker
>>> DB::connection()->getPdo();
>>> \App\Models\User::count();
>>> \App\Models\ServiceRequest::count();
```

## Performance Testing

### 1. Load Testing with Apache Bench
```bash
# Install Apache Bench (if not installed)
# Test API endpoint
ab -n 100 -c 10 http://localhost:8000/api/monitoring/health

# Test authenticated endpoint (add token)
ab -n 50 -c 5 -H "Authorization: Bearer YOUR_TOKEN" http://localhost:8000/api/requests
```

### 2. Test with Artisan Commands
```bash
# Test cache performance
php artisan cache:clear
php artisan config:cache
php artisan route:cache

# Test queue system
php artisan queue:work

# Test performance metrics
php artisan monitoring:collect
```

## Testing Checklist

### Pre-Testing Setup
- [ ] Ensure Laravel dependencies are installed (`composer install`)
- [ ] Configure `.env` file with database credentials
- [ ] Run database migrations (`php artisan migrate`)
- [ ] Start development server (`php artisan serve`)

### Authentication Testing
- [ ] Register new user
- [ ] Login with valid credentials
- [ ] Login with invalid credentials
- [ ] Access protected endpoint without token
- [ ] Access protected endpoint with valid token

### Service Request Testing
- [ ] Create service request
- [ ] List service requests
- [ ] Get specific service request
- [ ] Update service request
- [ ] Accept service request (as provider)
- [ ] Complete service request
- [ ] Delete service request

### File Upload Testing
- [ ] Upload valid image file
- [ ] Upload valid document file
- [ ] Upload invalid file type
- [ ] Upload oversized file
- [ ] Download uploaded file
- [ ] Delete uploaded file

### Monitoring Testing
- [ ] Health check endpoint
- [ ] Metrics dashboard
- [ ] Performance metrics
- [ ] System metrics collection

### Error Handling Testing
- [ ] Validation errors (422)
- [ ] Authentication errors (401)
- [ ] Authorization errors (403)
- [ ] Not found errors (404)
- [ ] Rate limiting (429)

### Performance Testing
- [ ] Response time under 100ms for cached endpoints
- [ ] Response time under 500ms for database queries
- [ ] Memory usage monitoring
- [ ] Database query optimization

## Troubleshooting

### Common Issues

#### 1. Server Not Starting
```bash
# Check if port 8000 is in use
netstat -ano | findstr :8000

# Kill process using port 8000
taskkill /PID <PID> /F

# Use different port
php artisan serve --port=8001
```

#### 2. Database Connection Issues
```bash
# Check database credentials in .env
php artisan config:cache
php artisan config:clear

# Test database connection
php artisan tinker
>>> DB::connection()->getDatabaseName();
```

#### 3. Permission Issues
```bash
# Clear caches
php artisan cache:clear
php artisan config:clear
php artisan route:clear

# Check file permissions
chmod -R 755 storage/
chmod -R 755 bootstrap/cache/
```

#### 4. Composer Issues
```bash
# Update composer
composer update

# Install specific packages
composer require laravel/sanctum
composer require spatie/laravel-permission
```

## Advanced Testing

### 1. Unit Testing
```bash
# Run unit tests only
php artisan test --testsuite=Unit

# Run specific unit test
php artisan test tests/Unit/DescriptionEnhancerServiceTest.php
```

### 2. Feature Testing
```bash
# Run feature tests only
php artisan test --testsuite=Feature

# Run with database transactions
php artisan test --testsuite=Feature --env=testing
```

### 3. Browser Testing (if using Laravel Dusk)
```bash
# Install Dusk
composer require --dev laravel/dusk

# Run browser tests
php artisan dusk
```

This comprehensive testing guide covers all aspects of testing your Laravel backend API. Start with the basic curl commands and Postman collection, then move to more advanced testing as needed.
