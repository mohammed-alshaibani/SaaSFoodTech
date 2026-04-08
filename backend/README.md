# SaaSFoodTech Service Marketplace Platform

A comprehensive service marketplace platform that connects customers with service providers, featuring advanced RBAC, AI-powered enhancements, and real-time notifications.

## 🚀 Features

### Core Functionality
- **Service Request Management**: Create, view, and manage service requests
- **Geolocation-based Matching**: Find nearby service requests within specified radius
- **Role-based Access Control**: Admin, Provider, and Customer roles with dynamic permissions
- **Subscription Model**: Free and paid plans with usage limits
- **AI-Powered Features**: Request categorization and pricing suggestions

### Advanced Features
- **Real-time Notifications**: WebSocket-based status updates
- **File Attachments**: Support for request documentation
- **Background Processing**: Async notifications and AI processing
- **Comprehensive API**: RESTful API with JWT authentication
- **Production Ready**: Docker containerization, caching, and monitoring

## 📋 System Requirements

- PHP 8.2+
- MySQL 8.0+ or PostgreSQL 13+
- Redis 6.0+
- Node.js 18+ (for frontend)
- Docker & Docker Compose (optional)

## 🛠️ Setup Instructions

### Using Docker (Recommended)

1. **Clone the repository**
```bash
git clone <repository-url>
cd saas-foodtech-platform
```

2. **Start services with Docker Compose**
```bash
docker-compose up -d
```

3. **Install dependencies and setup**
```bash
docker-compose exec backend composer install
docker-compose exec backend php artisan key:generate
docker-compose exec backend php artisan migrate
docker-compose exec backend php artisan db:seed
```

### Manual Setup

1. **Backend Setup**
```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan db:seed
php artisan serve
```

2. **Frontend Setup**
```bash
cd frontend
npm install
npm run dev
```

3. **Queue Worker**
```bash
php artisan queue:work
```

## 🏗️ Architecture Overview

### Backend (Laravel)
- **Framework**: Laravel 11
- **Authentication**: JWT (JSON Web Tokens)
- **Database**: MySQL with Redis caching
- **Queue System**: Redis with Horizon
- **File Storage**: Local storage (configurable for S3)

### Frontend (Next.js)
- **Framework**: Next.js 14 with App Router
- **Styling**: Tailwind CSS
- **State Management**: React Context
- **HTTP Client**: Axios

### Database Schema
- **Users**: Authentication and role management
- **Service Requests**: Core business entity with geolocation
- **Attachments**: File management for requests
- **Permissions**: Dynamic RBAC system
- **Subscriptions**: Plan management and usage tracking

## 🔐 RBAC Design

### Role Hierarchy
1. **Admin**: Full system access
2. **Provider Admin**: Can manage other providers
3. **Provider**: Can accept and complete requests
4. **Customer**: Can create and manage own requests

### Dynamic Permissions
- `request.create`: Create service requests
- `request.accept`: Accept service requests
- `request.complete`: Mark requests as completed
- `request.view_all`: View all requests (admin only)
- `user.manage`: Manage user permissions (admin only)

### Permission Enforcement
- **Middleware**: `CheckPermission` middleware validates permissions
- **Policies**: Model-based authorization policies
- **API Level**: All endpoints enforce permission checks

## 🎯 Key Design Decisions

### Technology Choices
- **Laravel**: Rapid development with robust ecosystem
- **Next.js**: Modern React framework with SSR support
- **JWT**: Stateless authentication for API scalability
- **Redis**: High-performance caching and queue management

### Trade-offs
- **Simplicity over Complexity**: Focused on core features first
- **Monolithic Architecture**: Suitable for MVP scale
- **Mock Payment Integration**: Simplified subscription model
- **AI Integration**: External API calls with fallback logic

### Performance Considerations
- **Database Indexing**: Optimized for geolocation queries
- **Caching Strategy**: Redis for frequently accessed data
- **Queue Processing**: Async operations for better response times
- **Rate Limiting**: Subscription-based API throttling

## 📚 API Documentation

### Base URL
- **Development**: `http://localhost:8000/api`
- **Production**: `https://api.saasfoodtech.com/api`

### Authentication
All API endpoints require JWT authentication:
```
Authorization: Bearer {token}
```

### Key Endpoints
- `POST /register` - User registration
- `POST /login` - User authentication
- `GET /service-requests` - List service requests
- `POST /service-requests` - Create service request
- `POST /service-requests/{id}/accept` - Accept request
- `GET /subscription/plans` - List subscription plans

For complete API documentation, see [API_DOCUMENTATION.md](API_DOCUMENTATION.md)

## 🧪 Testing

### Running Tests
```bash
# Backend tests
php artisan test

# Frontend tests
npm test

# Feature tests
php artisan test --testsuite=Feature
```

### Test Coverage
- Authentication and authorization
- Service request lifecycle
- RBAC permissions
- API endpoints
- Business logic validation

## 📦 Deployment

### Environment Variables
Key environment variables to configure:
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=saas_foodtech
DB_USERNAME=username
DB_PASSWORD=password

JWT_SECRET=your-jwt-secret
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
```

### Production Deployment
1. Configure environment variables
2. Run database migrations: `php artisan migrate`
3. Optimize application: `php artisan optimize`
4. Start queue workers: `php artisan queue:work --daemon`

## 🔧 What Would Be Improved With More Time

### Performance Optimizations
- Implement database read replicas
- Add CDN for static assets
- Implement API response caching
- Optimize database queries with eager loading

### Feature Enhancements
- Real-time map integration
- Advanced filtering and search
- Payment gateway integration
- Multi-language support
- Mobile applications

### Infrastructure Improvements
- Microservices architecture
- Kubernetes deployment
- Advanced monitoring and logging
- Automated scaling policies

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Add tests for new functionality
5. Submit a pull request

## 📄 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## 📞 Support

For support and questions, please contact the development team or create an issue in the repository.

---

**Built with ❤️ using Laravel and Next.js**
