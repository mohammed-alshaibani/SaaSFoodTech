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
1. **Admin**: Full system access, can manage all users and permissions
2. **Provider Admin**: Can manage permissions for other providers (employees)
3. **Provider Employee**: Limited provider permissions, can accept/complete requests
4. **Customer**: Can create and manage own requests only

### Dynamic Permissions System
The RBAC system is designed to be flexible and dynamic:

#### **Permission Storage**
- **Role-based permissions**: Stored in `role_has_permissions` table
- **Direct user permissions**: Stored in `user_permissions` table (overrides roles)
- **Permission categories**: Organized by feature area (request, user, admin, subscription)
- **Permission scopes**: Define context-specific permissions

#### **Core Permissions**
- `request.create`: Create service requests
- `request.accept`: Accept service requests
- `request.complete`: Mark requests as completed
- `request.view_all`: View all requests (admin only)
- `user.manage`: Manage user permissions (admin only)
- `permission.assign`: Assign permissions to users (admin/provider admin only)
- `subscription.upgrade`: Upgrade subscription plans

#### **Permission Enforcement Layers**
1. **Middleware Layer**: `CheckPermission` middleware validates permissions before controller execution
2. **Policy Layer**: Model-based authorization policies provide fine-grained access control
3. **API Level**: All endpoints enforce permission checks with proper error responses
4. **Database Level**: Row-level security through query scopes

#### **Dynamic Permission Assignment**
- **Admin**: Can assign any permission to any user
- **Provider Admin**: Can assign provider-level permissions to other providers
- **Audit Trail**: All permission changes are logged in `role_permissions_audit` table
- **Temporary Permissions**: Support for time-limited permission grants

#### **Permission Resolution Logic**
1. Check direct user permissions first (highest priority)
2. Fall back to role-based permissions
3. Apply permission inheritance through role hierarchy
4. Respect permission denial overrides

#### **Security Features**
- **Permission Escalation Prevention**: Users cannot grant themselves higher permissions
- **Scope Validation**: Permissions are validated against user context
- **Rate Limiting**: Permission checks are cached to prevent abuse
- **Audit Logging**: All permission decisions are logged for security review

## 🎯 Key Design Decisions

### Technology Choices
- **Laravel**: Rapid development with robust ecosystem
- **Next.js**: Modern React framework with SSR support
- **JWT**: Stateless authentication for API scalability
- **Redis**: High-performance caching and queue management

### Trade-offs
- **Simplicity over Complexity**: Prioritized core request lifecycle and geolocation over complex UI features like maps.
- **Monolithic Architecture**: Chose a single Laravel backend for ease of deployment and lower overhead for an MVP.
- **Mock Payment Integration**: Provided a flexible `PaymentProcessorInterface` but only implemented a `MockPaymentProcessor` to avoid external dependencies while showing extensibility.
- **AI Integration**: Implemented AI features synchronously in controllers for immediate feedback, though background jobs are available for scaling.
- **Security Middleware**: Balanced between aggressive security (SQLi/XSS prevention) and system usability, choosing to implement custom regex-based filters for immediate protection.

### Performance Considerations
- **Database Indexing**: Optimized for geolocation queries using latitude/longitude indexing.
- **Caching Strategy**: Redis used for frequently accessed configuration and basic rate limiting.
- **Queue Processing**: Prepared for async operations (AI, Notifications) to keep user interaction snappy.
- **Rate Limiting**: Implemented multi-tier rate limiting (global, per-plan, and AI-specific).

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
GEMINI_API_KEY=your_gemini_api_key
```

### CI/CD Pipeline
This project includes a comprehensive CI/CD pipeline using GitHub Actions:

#### **Automated Testing & Quality Checks**
- **Backend Testing**: PHPUnit with MySQL database
- **Frontend Testing**: Jest/React component tests & build verification
- **Code Quality**: PHPStan, PHP CodeSniffer, Laravel Pint
- **Security Scanning**: Dependency vulnerability detection
- **Build Verification**: Docker image building tests

#### **Automated Deployment**
- **Docker Image Building**: Automated multi-stage builds
- **Production Deployment**: Zero-downtime deployments
- **Health Monitoring**: Post-deployment verification
- **Rollback Capability**: Automatic rollback on failure

#### **Pipeline Triggers**
```yaml
# Push to develop → CI only (testing)
# Push to main → CI + CD (deploy to production)
# Git tags → CI + CD (versioned releases)
```

### Production Deployment

#### **Option 1: Automated Deployment (Recommended)**
```bash
# The CI/CD pipeline handles deployment automatically when:
# - Pushing to main branch
# - Creating a git tag (v1.0.0, v1.1.0, etc.)

# Manual deployment trigger:
curl -X POST \
  -H "Authorization: token $GITHUB_TOKEN" \
  -H "Accept: application/vnd.github.v3+json" \
  https://api.github.com/repos/owner/repo/actions/workflows/deploy/dispatches \
  -d '{"ref":"main","inputs":{"environment":"production"}}'
```

#### **Option 2: Manual Deployment**
```bash
# Use the deployment scripts
./scripts/deploy.sh deploy          # Deploy latest version
./scripts/deploy.sh deploy v1.2.0  # Deploy specific version
./scripts/deploy.sh rollback        # Rollback to previous version
```

#### **Option 3: Health Monitoring**
```bash
# Continuous health monitoring
./scripts/health-check.sh watch          # Watch mode (updates every 60s)
./scripts/health-check.sh report         # One-time health report
./scripts/health-check.sh detailed mysql # Detailed service information
```

### Required GitHub Secrets
Configure these in GitHub repository settings:
```bash
DOCKER_USERNAME=your_dockerhub_username
DOCKER_PASSWORD=your_dockerhub_password
PROD_HOST=production_server_ip
PROD_USER=deploy_user
PROD_SSH_KEY=private_ssh_key
APP_URL=https://yourdomain.com
FRONTEND_URL=https://yourdomain.com
SLACK_WEBHOOK=your_slack_webhook_url
```

### Production Environment Variables
```env
DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=saas_foodtech
DB_USERNAME=saas_user
DB_PASSWORD=secure_password
JWT_SECRET=production_jwt_secret
REDIS_HOST=redis
REDIS_PASSWORD=redis_password
REDIS_PORT=6379
GEMINI_API_KEY=production_gemini_key
APP_URL=https://yourdomain.com
FRONTEND_URL=https://yourdomain.com
LOG_CHANNEL=stack
MAIL_MAILER=smtp
MAIL_HOST=your_smtp_host
MAIL_PORT=587
MAIL_USERNAME=your_email_username
MAIL_PASSWORD=your_email_password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@yourdomain.com
```

### Production Deployment Steps
1. Configure environment variables
2. Set up GitHub secrets
3. Push to main branch (triggers automatic deployment)
4. Monitor deployment via GitHub Actions
5. Verify deployment with health check script

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
