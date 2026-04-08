# Otex Service Platform API Documentation

## Overview

The Otex Service Platform API is a RESTful API that connects customers with service providers. This comprehensive platform allows users to create, manage, and complete service requests with advanced features including AI-powered enhancements, real-time notifications, and file attachments.

## Base URL

- **Development**: `http://localhost:8000/api`
- **Production**: `https://api.otex.com/api`

## Authentication

The API uses JWT (JSON Web Token) authentication. Include the token in the Authorization header:

```
Authorization: Bearer {token}
```

### Getting a Token

1. **Register** a new user account
2. **Login** with your credentials to receive a JWT token
3. Include the token in all subsequent API requests

## Rate Limiting

Rate limits are applied based on user subscription plan:

- **Free Plan**: 100 requests/hour
- **Basic Plan**: 500 requests/hour  
- **Premium Plan**: 1000 requests/hour
- **Enterprise Plan**: Unlimited requests

Rate limit headers are included in responses:
- `X-RateLimit-Limit`: Request limit for the current window
- `X-RateLimit-Remaining`: Remaining requests in current window
- `X-RateLimit-Reset`: Time when the rate limit window resets

## API Endpoints

### Authentication

#### Register User
```http
POST /register
```

**Request Body:**
```json
{
  "name": "John Doe",
  "email": "john@example.com",
  "password": "Password123!",
  "password_confirmation": "Password123!",
  "role": "customer",
  "phone": "+1234567890",
  "company_name": "John's Services"
}
```

**Response (201):**
```json
{
  "success": true,
  "access_token": "1|abc123...",
  "token_type": "Bearer",
  "user": {
    "id": 1,
    "name": "John Doe",
    "email": "john@example.com",
    "plan": "free",
    "roles": ["customer"],
    "permissions": ["create-service-request"]
  },
  "request_id": "req_1234567890",
  "timestamp": "2023-12-01T12:00:00Z"
}
```

#### Login User
```http
POST /login
```

**Request Body:**
```json
{
  "email": "john@example.com",
  "password": "Password123!"
}
```

**Response (200):**
```json
{
  "success": true,
  "access_token": "1|abc123...",
  "token_type": "Bearer",
  "user": {
    "id": 1,
    "name": "John Doe",
    "email": "john@example.com",
    "plan": "free",
    "roles": ["customer"],
    "permissions": ["create-service-request"]
  },
  "request_id": "req_1234567890",
  "timestamp": "2023-12-01T12:00:00Z"
}
```

#### Get Current User
```http
GET /me
Authorization: Bearer {token}
```

**Response (200):**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "name": "John Doe",
    "email": "john@example.com",
    "plan": "free",
    "roles": ["customer"],
    "permissions": ["create-service-request"]
  },
  "request_id": "req_1234567890",
  "timestamp": "2023-12-01T12:00:00Z"
}
```

#### Logout User
```http
POST /logout
Authorization: Bearer {token}
```

**Response (200):**
```json
{
  "success": true,
  "message": "Logged out successfully.",
  "request_id": "req_1234567890",
  "timestamp": "2023-12-01T12:00:00Z"
}
```

### Service Requests

#### List Service Requests
```http
GET /requests
Authorization: Bearer {token}
```

**Query Parameters:**
- `status` (string): Filter by status (pending, accepted, completed)
- `category` (string): Filter by category
- `latitude` (float): Filter by location (required with longitude)
- `longitude` (float): Filter by location (required with latitude)
- `radius` (float): Search radius in km (default: 50)
- `page` (integer): Page number (default: 1)
- `per_page` (integer): Items per page (default: 15)

**Response (200):**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "title": "Fix my kitchen sink",
      "description": "The kitchen sink is leaking and needs professional repair.",
      "status": "pending",
      "latitude": 40.7128,
      "longitude": -74.0060,
      "customer": {
        "id": 1,
        "name": "John Doe",
        "email": "john@example.com"
      },
      "provider": null,
      "attachments": [],
      "metadata": {
        "category": "plumbing",
        "urgency": "normal"
      },
      "created_at": "2023-12-01T12:00:00Z",
      "updated_at": "2023-12-01T12:00:00Z"
    }
  ],
  "links": {
    "first": "http://localhost:8000/api/requests?page=1",
    "last": "http://localhost:8000/api/requests?page=1",
    "prev": null,
    "next": null
  },
  "meta": {
    "current_page": 1,
    "from": 1,
    "last_page": 1,
    "per_page": 15,
    "to": 1,
    "total": 1
  },
  "request_id": "req_1234567890",
  "timestamp": "2023-12-01T12:00:00Z"
}
```

#### Create Service Request
```http
POST /requests
Authorization: Bearer {token}
```

**Request Body:**
```json
{
  "title": "Fix my kitchen sink",
  "description": "The kitchen sink is leaking and needs professional repair.",
  "latitude": 40.7128,
  "longitude": -74.0060,
  "category": "plumbing",
  "urgency": "normal",
  "enhance_with_ai": false
}
```

**Response (201):**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "title": "Fix my kitchen sink",
    "description": "The kitchen sink is leaking and needs professional repair.",
    "status": "pending",
    "latitude": 40.7128,
    "longitude": -74.0060,
    "customer": {
      "id": 1,
      "name": "John Doe",
      "email": "john@example.com"
    },
    "provider": null,
    "attachments": [],
    "metadata": {
      "category": "plumbing",
      "urgency": "normal"
    },
    "created_at": "2023-12-01T12:00:00Z",
    "updated_at": "2023-12-01T12:00:00Z"
  },
  "request_id": "req_1234567890",
  "timestamp": "2023-12-01T12:00:00Z"
}
```

#### Get Service Request
```http
GET /requests/{id}
Authorization: Bearer {token}
```

**Response (200):**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "title": "Fix my kitchen sink",
    "description": "The kitchen sink is leaking and needs professional repair.",
    "status": "pending",
    "latitude": 40.7128,
    "longitude": -74.0060,
    "customer": {
      "id": 1,
      "name": "John Doe",
      "email": "john@example.com"
    },
    "provider": null,
    "attachments": [],
    "metadata": {
      "category": "plumbing",
      "urgency": "normal"
    },
    "created_at": "2023-12-01T12:00:00Z",
    "updated_at": "2023-12-01T12:00:00Z"
  },
  "request_id": "req_1234567890",
  "timestamp": "2023-12-01T12:00:00Z"
}
```

#### Accept Service Request
```http
PATCH /requests/{id}/accept
Authorization: Bearer {token}
```

**Request Body:**
```json
{
  "provider_notes": "I can fix this within 2 hours. I have 10 years of plumbing experience.",
  "estimated_completion": "2023-12-02"
}
```

**Response (200):**
```json
{
  "success": true,
  "message": "Request accepted successfully.",
  "data": {
    "id": 1,
    "title": "Fix my kitchen sink",
    "status": "accepted",
    "provider": {
      "id": 2,
      "name": "Jane Smith",
      "email": "jane@provider.com"
    },
    "metadata": {
      "provider_notes": "I can fix this within 2 hours.",
      "estimated_completion": "2023-12-02"
    }
  },
  "request_id": "req_1234567890",
  "timestamp": "2023-12-01T12:00:00Z"
}
```

#### Complete Service Request
```http
PATCH /requests/{id}/complete
Authorization: Bearer {token}
```

**Request Body:**
```json
{
  "completion_notes": "Successfully replaced the faucet and fixed the leak.",
  "final_attachments": [1, 2],
  "rating": 5
}
```

**Response (200):**
```json
{
  "success": true,
  "message": "Request marked as completed.",
  "data": {
    "id": 1,
    "title": "Fix my kitchen sink",
    "status": "completed",
    "metadata": {
      "completion_notes": "Successfully replaced the faucet and fixed the leak.",
      "rating": 5,
      "completed_at": "2023-12-01T14:00:00Z"
    }
  },
  "request_id": "req_1234567890",
  "timestamp": "2023-12-01T12:00:00Z"
}
```

### File Attachments

#### Upload File
```http
POST /attachments/upload
Authorization: Bearer {token}
Content-Type: multipart/form-data
```

**Request Body:**
```
file: [binary file data]
service_request_id: 1
```

**Response (201):**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "filename": "kitchen_sink_20231201_123456.jpg",
    "original_filename": "kitchen_sink.jpg",
    "file_type": "image",
    "file_size": 1048576,
    "mime_type": "image/jpeg",
    "download_url": "http://localhost:8000/api/attachments/1/download"
  },
  "request_id": "req_1234567890",
  "timestamp": "2023-12-01T12:00:00Z"
}
```

#### Download File
```http
GET /attachments/{id}/download
Authorization: Bearer {token}
```

**Response (200):**
- Content-Type: application/octet-stream
- Content-Disposition: attachment; filename="original_filename.ext"
- Binary file data

#### Delete File
```http
DELETE /attachments/{id}
Authorization: Bearer {token}
```

**Response (200):**
```json
{
  "success": true,
  "message": "File deleted successfully.",
  "request_id": "req_1234567890",
  "timestamp": "2023-12-01T12:00:00Z"
}
```

### AI Services

#### Enhance Description
```http
POST /ai/enhance
Authorization: Bearer {token}
```

**Request Body:**
```json
{
  "title": "Fix my kitchen sink",
  "description": "The sink is broken"
}
```

**Response (200):**
```json
{
  "success": true,
  "data": {
    "original_description": "The sink is broken",
    "enhanced_description": "The kitchen sink is experiencing a significant leak and requires immediate professional plumbing services to address water damage and restore proper functionality.",
    "improvements": [
      "Added specific details about the issue",
      "Included urgency context",
      "Professional terminology",
      "Expanded scope of work"
    ]
  },
  "request_id": "req_1234567890",
  "timestamp": "2023-12-01T12:00:00Z"
}
```

## Error Responses

All error responses follow this structure:

```json
{
  "success": false,
  "error": {
    "code": "ERROR_CODE",
    "message": "Human-readable error message",
    "details": {
      "field": ["Specific error details"]
    }
  },
  "request_id": "req_1234567890",
  "timestamp": "2023-12-01T12:00:00Z"
}
```

### Common Error Codes

- `UNAUTHENTICATED` (401): Invalid or missing authentication token
- `FORBIDDEN` (403): Insufficient permissions
- `NOT_FOUND` (404): Resource not found
- `VALIDATION_ERROR` (422): Invalid request data
- `RATE_LIMIT_EXCEEDED` (429): Too many requests
- `SERVER_ERROR` (500): Internal server error

## WebSocket Events

The API supports real-time WebSocket events for live updates:

### Connection
```
ws://localhost:8000/ws
Authorization: Bearer {token}
```

### Events

#### Service Request Created
```json
{
  "event": "service.request.created",
  "data": {
    "id": 1,
    "title": "Fix my kitchen sink",
    "customer": { "id": 1, "name": "John Doe" },
    "latitude": 40.7128,
    "longitude": -74.0060,
    "category": "plumbing"
  }
}
```

#### Service Request Accepted
```json
{
  "event": "service.request.accepted",
  "data": {
    "id": 1,
    "provider": { "id": 2, "name": "Jane Smith" },
    "provider_notes": "I can fix this within 2 hours."
  }
}
```

#### Service Request Completed
```json
{
  "event": "service.request.completed",
  "data": {
    "id": 1,
    "rating": 5,
    "completion_notes": "Successfully replaced the faucet."
  }
}
```

## SDKs and Libraries

### JavaScript/TypeScript
```bash
npm install @otex/api-client
```

```javascript
import { OtexAPI } from '@otex/api-client';

const api = new OtexAPI({
  baseURL: 'http://localhost:8000/api',
  token: 'your-jwt-token'
});

// Create service request
const request = await api.requests.create({
  title: 'Fix my kitchen sink',
  description: 'The sink is leaking',
  latitude: 40.7128,
  longitude: -74.0060
});
```

### Python
```bash
pip install otex-api
```

```python
from otex_api import OtexAPI

api = OtexAPI(
    base_url='http://localhost:8000/api',
    token='your-jwt-token'
)

# Create service request
request = api.requests.create({
    'title': 'Fix my kitchen sink',
    'description': 'The sink is leaking',
    'latitude': 40.7128,
    'longitude': -74.0060
})
```

## Testing

The API includes comprehensive test suites:

### Running Tests
```bash
# Run all tests
php artisan test

# Run specific test file
php artisan test tests/Feature/ServiceRequestTest.php

# Run with coverage
php artisan test --coverage
```

### Test Environment
- Uses SQLite in-memory database
- Mocked external services (OpenAI, email, etc.)
- Factory patterns for test data
- Comprehensive assertions for API responses

## Support

For API support and questions:

- **Email**: api-support@otex.com
- **Documentation**: https://docs.otex.com/api
- **Status Page**: https://status.otex.com
- **GitHub Issues**: https://github.com/otex/platform/issues

## Changelog

### v1.0.0 (2023-12-01)
- Initial API release
- Authentication and authorization
- Service request management
- File attachments
- AI-powered description enhancement
- Real-time WebSocket events
- Comprehensive testing suite

---

*This documentation is version 1.0.0. For the latest updates, visit https://docs.otex.com/api*
