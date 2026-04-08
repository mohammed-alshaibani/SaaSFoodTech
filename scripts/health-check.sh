#!/bin/bash

# Health Check Script for SaaS FoodTech Platform
# Monitors all services and reports their status

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

# Configuration
APP_URL="${APP_URL:-http://localhost}"
SERVICES=("backend" "frontend" "mysql" "redis" "nginx")
LOG_FILE="/var/log/health-check.log"

# Functions
log() {
    echo "$(date '+%Y-%m-%d %H:%M:%S') - $1" | tee -a "$LOG_FILE"
}

check_service() {
    local service=$1
    local url=$2
    local expected_status=${3:-200}
    
    log "Checking $service service..."
    
    if curl -f -s -w "%{http_code}" "$url" 2>/dev/null | grep -q "$expected_status"; then
        echo -e "${GREEN}✅ $service: HEALTHY${NC}"
        return 0
    else
        echo -e "${RED}❌ $service: UNHEALTHY${NC}"
        return 1
    fi
}

check_docker_container() {
    local container_name=$1
    local expected_status=${2:-running}
    
    if docker ps --format "table {{.Names}}\t{{.Status}}" | grep -q "$container_name.*$expected_status"; then
        echo -e "${GREEN}✅ Container $container_name: $expected_status${NC}"
        return 0
    else
        echo -e "${RED}❌ Container $container_name: NOT $expected_status${NC}"
        return 1
    fi
}

check_database_connection() {
    log "Checking database connection..."
    
    if docker exec saasfoodtech_mysql_prod mysqladmin ping -h localhost -u root -p"$DB_PASSWORD" --silent; then
        echo -e "${GREEN}✅ MySQL: CONNECTED${NC}"
        return 0
    else
        echo -e "${RED}❌ MySQL: DISCONNECTED${NC}"
        return 1
    fi
}

check_redis_connection() {
    log "Checking Redis connection..."
    
    if docker exec saasfoodtech_redis_prod redis-cli ping; then
        echo -e "${GREEN}✅ Redis: CONNECTED${NC}"
        return 0
    else
        echo -e "${RED}❌ Redis: DISCONNECTED${NC}"
        return 1
    fi
}

check_disk_space() {
    log "Checking disk space..."
    
    local usage=$(df / | awk 'NR==2 {print $5}' | sed 's/%//')
    local available=$(df -h / | awk 'NR==2 {print $4}')
    
    if [ "${usage%.*}" -lt 80 ]; then
        echo -e "${GREEN}✅ Disk Space: ${available} available (${usage}% used)${NC}"
        return 0
    else
        echo -e "${YELLOW}⚠️ Disk Space: ${available} available (${usage}% used)${NC}"
        return 1
    fi
}

check_memory_usage() {
    log "Checking memory usage..."
    
    local mem_usage=$(free | awk 'NR==2{printf "%.0f", $3*100/$2}')
    
    if [ "${mem_usage%.*}" -lt 80 ]; then
        echo -e "${GREEN}✅ Memory Usage: ${mem_usage}%${NC}"
        return 0
    else
        echo -e "${YELLOW}⚠️ Memory Usage: ${mem_usage}%${NC}"
        return 1
    fi
}

generate_health_report() {
    local overall_status=0
    
    echo -e "${BLUE}=== SaaS FoodTech Platform Health Report ===${NC}"
    echo -e "${BLUE}Generated at: $(date)${NC}"
    echo ""
    
    # Check Docker containers
    echo -e "${BLUE}📦 Docker Containers:${NC}"
    for service in "${SERVICES[@]}"; do
        if ! check_docker_container "saasfoodtech_${service}_prod"; then
            overall_status=1
        fi
    done
    echo ""
    
    # Check service endpoints
    echo -e "${BLUE}🌐 Service Endpoints:${NC}"
    if ! check_service "Backend API" "$APP_URL/health"; then
        overall_status=1
    fi
    
    if ! check_service "Frontend" "$APP_URL"; then
        overall_status=1
    fi
    echo ""
    
    # Check database connections
    echo -e "${BLUE}🗄️ Database Connections:${NC}"
    if ! check_database_connection; then
        overall_status=1
    fi
    
    if ! check_redis_connection; then
        overall_status=1
    fi
    echo ""
    
    # Check system resources
    echo -e "${BLUE}💻 System Resources:${NC}"
    if ! check_disk_space; then
        overall_status=1
    fi
    
    if ! check_memory_usage; then
        overall_status=1
    fi
    echo ""
    
    # Overall status
    if [ $overall_status -eq 0 ]; then
        echo -e "${GREEN}🎉 Overall Status: HEALTHY${NC}"
        return 0
    else
        echo -e "${RED}🚨 Overall Status: UNHEALTHY${NC}"
        return 1
    fi
}

# Detailed service check
detailed_check() {
    local service=$1
    
    case $service in
        "backend")
            echo -e "${BLUE}=== Backend Service Details ===${NC}"
            docker exec saasfoodtech_backend_prod php artisan --version
            docker exec saasfoodtech_backend_prod php artisan route:list | head -10
            docker logs --tail 50 saasfoodtech_backend_prod
            ;;
        "frontend")
            echo -e "${BLUE}=== Frontend Service Details ===${NC}"
            docker logs --tail 50 saasfoodtech_nginx_prod
            ;;
        "mysql")
            echo -e "${BLUE}=== MySQL Database Details ===${NC}"
            docker exec saasfoodtech_mysql_prod mysql -V
            docker exec saasfoodtech_mysql_prod mysql -e "SHOW PROCESSLIST;"
            ;;
        "redis")
            echo -e "${BLUE}=== Redis Cache Details ===${NC}"
            docker exec saasfoodtech_redis_prod redis-server --version
            docker exec saasfoodtech_redis_prod redis-cli info
            ;;
        *)
            echo "Available services: backend, frontend, mysql, redis"
            return 1
            ;;
    esac
}

# Main script logic
main() {
    case "${1:-report}" in
        "report")
            generate_health_report
            ;;
        "detailed")
            if [ -z "$2" ]; then
                echo "Usage: $0 detailed [service_name]"
                echo "Available services: backend, frontend, mysql, redis"
                exit 1
            fi
            detailed_check "$2"
            ;;
        "watch")
            echo -e "${BLUE}👁 Watching health status (Ctrl+C to stop)${NC}"
            while true; do
                generate_health_report
                sleep 60
            done
            ;;
        *)
            echo "Usage: $0 [report|detailed|watch] [service_name]"
            echo "  report    - Generate overall health report"
            echo "  detailed  - Show detailed info for specific service"
            echo "  watch     - Continuous monitoring (updates every 60s)"
            exit 1
            ;;
    esac
}

# Script entry point
main "$@"
