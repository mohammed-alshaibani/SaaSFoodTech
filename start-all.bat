@echo off
echo 🚀 Starting FoodTech SAAS Platform - All Services...

REM Check if Docker is running
docker info >nul 2>&1
if %errorlevel% neq 0 (
    echo ❌ Docker is not running. Please start Docker Desktop first.
    pause
    exit /b 1
)

REM Build and start all services
echo 📦 Building containers...
docker-compose build --no-cache

echo 🔄 Starting all services...
docker-compose up -d

REM Wait for services to be ready
echo ⏳ Waiting for services to start...
timeout /t 30 /nobreak

REM Check service status
echo 📊 Checking service status...
docker-compose ps

REM Run database migrations and seeding
echo 🗄️ Running database migrations...
docker-compose exec -T backend php artisan migrate --force

echo 🌱 Seeding database...
docker-compose exec -T backend php artisan db:seed --force

REM Generate API documentation
echo 📚 Generating API documentation...
docker-compose exec -T backend php artisan l5-swagger:generate --force

echo.
echo ✅ All services are ready!
echo.
echo 🌐 Access URLs:
echo    Backend API:        http://localhost:8000/api
echo    Frontend:           http://localhost:3001
echo    API Documentation:   http://localhost:8000/api/docs
echo    MySQL (external):   localhost:3307
echo    Redis (external):   localhost:6379
echo    Mailpit (email):    http://localhost:8025
echo    Redis Commander:    http://localhost:8081
echo.
echo 🔑 Test Users:
echo    Admin:   admin@test.com / password
echo    Customer: customer@test.com / password
echo.
echo 📋 Useful Commands:
echo    View logs:          docker-compose logs -f
echo    Stop all:           docker-compose down
echo    Restart backend:      docker-compose restart backend
echo    Access backend:       docker-compose exec backend bash
echo    Access database:       docker-compose exec mysql mysql -u root saas_foodtech
pause
