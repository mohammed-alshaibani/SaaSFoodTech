# Docker & CI/CD Setup Guide

## Overview
This guide shows how to dockerize your Laravel/Next.js SaaS project and set up CI/CD using GitHub Actions with reusable workflows.

## Database Configuration
- **Database Name**: `saas_foodtech` (used consistently across all environments)
- **CI Database**: `saas_foodtech` (test environment)
- **Local Database**: `saas_foodtech` (development)

## Local Docker Setup

### Prerequisites
- Docker Desktop installed
- Git repository cloned

### Commands to Build and Run Locally

```bash
# 1. Stop and remove existing containers
docker-compose down -v

# 2. Build and start all services
docker-compose up --build -d

# 3. View logs
docker-compose logs -f

# 4. Access services
# Backend: http://localhost:8000
# Frontend: http://localhost:3001
# Mailpit: http://localhost:8025
# Redis Commander: http://localhost:8081
```

### Database Setup
```bash
# Run migrations
docker-compose exec backend php artisan migrate

# Seed database
docker-compose exec backend php artisan db:seed

# Check database connection
docker-compose exec backend php artisan tinker
```

## CI/CD Pipeline (GitHub Actions)

### Workflow Structure
```
.github/workflows/
  ci-reusable.yml      # Reusable CI pipeline
  ci-main.yml          # Main CI workflow (calls reusable)
  deploy-simple.yml    # Reusable deployment pipeline
  ci-updated.yml       # Updated CI with v4 actions
```

### How to Use Reusable Workflows

#### 1. Main CI Workflow (ci-main.yml)
```yaml
# This calls the reusable CI workflow
jobs:
  call-reusable-ci:
    uses: ./.github/workflows/ci-reusable.yml
    with:
      node-version: '18'
      php-version: '8.2'
    secrets:
      GEMINI_API_KEY: ${{ secrets.GEMINI_API_KEY }}
```

#### 2. Trigger CI Pipeline
The CI pipeline automatically triggers on:
- Push to `main` or `develop` branches
- Pull requests to `main` branch

#### 3. Manual Deployment
```yaml
# In your main workflow or separate deploy workflow
jobs:
  deploy:
    uses: ./.github/workflows/deploy-simple.yml
    with:
      environment: 'production'
      docker-registry: 'docker.io'
      backend-image: 'your-username/saas-foodtech-backend'
      frontend-image: 'your-username/saas-foodtech-frontend'
    secrets:
      DOCKER_USERNAME: ${{ secrets.DOCKER_USERNAME }}
      DOCKER_PASSWORD: ${{ secrets.DOCKER_PASSWORD }}
      PROD_HOST: ${{ secrets.PROD_HOST }}
      PROD_USER: ${{ secrets.PROD_USER }}
      PROD_SSH_KEY: ${{ secrets.PROD_SSH_KEY }}
      APP_KEY: ${{ secrets.APP_KEY }}
      JWT_SECRET: ${{ secrets.JWT_SECRET }}
      APP_URL: ${{ secrets.APP_URL }}
      DB_USERNAME: ${{ secrets.DB_USERNAME }}
      DB_PASSWORD: ${{ secrets.DB_PASSWORD }}
      GEMINI_API_KEY: ${{ secrets.GEMINI_API_KEY }}
```

## Required GitHub Secrets

### CI/CD Secrets
```bash
# GitHub Repository Settings > Secrets and variables > Actions
GEMINI_API_KEY=your_gemini_api_key_here
```

### Deployment Secrets
```bash
# Docker Hub
DOCKER_USERNAME=your_dockerhub_username
DOCKER_PASSWORD=your_dockerhub_password

# Server Access
PROD_HOST=your_server_ip_or_domain
PROD_USER=your_ssh_username
PROD_SSH_KEY=your_private_ssh_key
PROD_PORT=22

# Application
APP_KEY=base64:your_laravel_app_key
JWT_SECRET=your_jwt_secret
APP_URL=https://yourdomain.com
FRONTEND_URL=https://yourdomain.com

# Database
DB_USERNAME=your_db_username
DB_PASSWORD=your_db_password

# Email (optional)
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your_email@gmail.com
MAIL_PASSWORD=your_email_password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@yourdomain.com

# Reverb (WebSocket)
REVERB_APP_ID=your_reverb_app_id
REVERB_APP_KEY=your_reverb_app_key
REVERB_APP_SECRET=your_reverb_app_secret
```

## Quality Gates

The CI pipeline includes these quality gates:
1. **Backend Tests** - PHPUnit tests with coverage
2. **Frontend Tests** - Jest/React Testing Library
3. **Code Quality** - PHP CodeSniffer, PHPStan, Laravel Pint
4. **Security Scan** - Trivy vulnerability scanner
5. **Docker Build** - Test Docker image builds

## Deployment Process

### Automated Deployment
1. CI pipeline passes all quality gates
2. Docker images are built and pushed to registry
3. Deployment script runs on production server
4. Health checks verify deployment success

### Manual Deployment Commands
```bash
# On production server
cd /opt/saas-foodtech

# Pull latest images
docker-compose -f docker-compose.prod.yml pull

# Stop existing containers
docker-compose -f docker-compose.prod.yml down

# Start new containers
docker-compose -f docker-compose.prod.yml up -d

# Run migrations
docker-compose -f docker-compose.prod.yml exec -T backend php artisan migrate --force

# Clear caches
docker-compose -f docker-compose.prod.yml exec -T backend php artisan config:cache
docker-compose -f docker-compose.prod.yml exec -T backend php artisan route:cache
docker-compose -f docker-compose.prod.yml exec -T backend php artisan view:cache
```

## Health Monitoring

### Health Endpoint
- **URL**: `http://yourdomain.com/api/health`
- **Response**: JSON with health status
- **Status Codes**: 200 (healthy), 503 (unhealthy)

### Health Check Response
```json
{
  "success": true,
  "data": {
    "status": "healthy",
    "database": "connected",
    "redis": "connected",
    "timestamp": "2024-01-01T12:00:00Z"
  },
  "request_id": "req_123456",
  "timestamp": "2024-01-01T12:00:00Z"
}
```

## Troubleshooting

### Common Issues

#### 1. Database Connection
```bash
# Check database container
docker-compose logs mysql

# Test connection from backend
docker-compose exec backend php artisan tinker
>>> DB::connection()->getPdo()
```

#### 2. Frontend Build Issues
```bash
# Clear node modules and rebuild
docker-compose exec frontend rm -rf node_modules package-lock.json
docker-compose exec frontend npm install
docker-compose exec frontend npm run build
```

#### 3. CI Pipeline Failures
- Check GitHub Actions logs
- Verify all secrets are set correctly
- Ensure database name is `saas_foodtech`
- Check for syntax errors in workflows

#### 4. Deployment Failures
```bash
# Check server logs
docker-compose -f docker-compose.prod.yml logs

# Verify image pull
docker-compose -f docker-compose.prod.yml pull

# Check health status
curl -f http://localhost/api/health
```

## Verification Commands

### After Deployment
```bash
# 1. Check application health
curl -f https://yourdomain.com/api/health

# 2. Verify frontend is accessible
curl -f https://yourdomain.com

# 3. Test API endpoints
curl -f https://yourdomain.com/api/monitoring/health

# 4. Check database connectivity
docker-compose -f docker-compose.prod.yml exec backend php artisan tinker
>>> DB::connection()->getDatabaseName()
```

### Quality Gates Verification
```bash
# In GitHub Actions, check:
# 1. All jobs passed (green checkmarks)
# 2. Test coverage reports uploaded
# 3. Security scan completed
# 4. Docker images built successfully
# 5. Health checks passed
```

## Quick Start Summary

1. **Setup Local**: `docker-compose up --build -d`
2. **Run Tests**: `docker-compose exec backend php artisan test`
3. **Push Code**: Git push triggers CI pipeline
4. **Deploy**: Manual workflow_dispatch or automatic on main branch
5. **Verify**: Check health endpoint and application URLs

This setup ensures consistent `saas_foodtech` database usage across all environments and provides a robust CI/CD pipeline with reusable GitHub Actions workflows.
