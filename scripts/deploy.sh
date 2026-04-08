#!/bin/bash

# Production Deployment Script for SaaS FoodTech Platform
# Usage: ./deploy.sh [version]

set -e  # Exit on any error

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
VERSION=${1:-latest}
BACKUP_DIR="/opt/backups"
DEPLOY_DIR="/opt/saas-foodtech"
LOG_FILE="/var/log/deploy.log"

# Functions
log() {
    echo "$(date '+%Y-%m-%d %H:%M:%S') - $1" | tee -a "$LOG_FILE"
}

error_exit() {
    echo -e "${RED}ERROR: $1${NC}"
    exit 1
}

success_msg() {
    echo -e "${GREEN}SUCCESS: $1${NC}"
}

warning_msg() {
    echo -e "${YELLOW}WARNING: $1${NC}"
}

info_msg() {
    echo -e "${BLUE}INFO: $1${NC}"
}

# Pre-deployment checks
check_prerequisites() {
    log "Starting deployment pre-checks"
    
    # Check if running as root
    if [[ $EUID -ne 0 ]]; then
        error_exit "This script must be run as root"
    fi
    
    # Check if Docker is running
    if ! docker info > /dev/null 2>&1; then
        error_exit "Docker is not running"
    fi
    
    # Check if docker-compose is available
    if ! command -v docker-compose &> /dev/null; then
        error_exit "docker-compose is not installed"
    fi
    
    success_msg "Prerequisites check passed"
}

# Backup current deployment
backup_current() {
    log "Creating backup of current deployment"
    
    mkdir -p "$BACKUP_DIR"
    
    # Backup database
    if docker ps | grep -q "saasfoodtech_mysql_prod"; then
        log "Backing up database"
        docker exec saasfoodtech_mysql_prod mysqldump -u root -p"$DB_PASSWORD" saas_foodtech > "$BACKUP_DIR/db_backup_$(date +%Y%m%d_%H%M%S).sql"
    fi
    
    # Backup configuration files
    if [ -f "$DEPLOY_DIR/docker-compose.prod.yml" ]; then
        cp "$DEPLOY_DIR/docker-compose.prod.yml" "$BACKUP_DIR/docker-compose_backup_$(date +%Y%m%d_%H%M%S).yml"
    fi
    
    success_msg "Backup completed"
}

# Deploy new version
deploy_version() {
    log "Starting deployment of version: $VERSION"
    
    # Navigate to deployment directory
    cd "$DEPLOY_DIR"
    
    # Download latest docker-compose if version is "latest"
    if [ "$VERSION" = "latest" ]; then
        log "Downloading latest docker-compose configuration"
        curl -o docker-compose.prod.yml.new https://raw.githubusercontent.com/${GITHUB_REPOSITORY}/main/docker-compose.prod.yml
    else
        log "Downloading version $VERSION configuration"
        curl -o docker-compose.prod.yml.new https://raw.githubusercontent.com/${GITHUB_REPOSITORY}/v$VERSION/docker-compose.prod.yml
    fi
    
    # Validate new configuration
    if ! docker-compose -f docker-compose.prod.yml.new config > /dev/null; then
        error_exit "Invalid docker-compose configuration"
    fi
    
    # Update image tags in configuration
    sed -i "s|yourusername/saas-foodtech-backend:.*|yourusername/saas-foodtech-backend:$VERSION|g" docker-compose.prod.yml.new
    sed -i "s|yourusername/saas-foodtech-frontend:.*|yourusername/saas-foodtech-frontend:$VERSION|g" docker-compose.prod.yml.new
    
    # Stop current services
    log "Stopping current services"
    docker-compose -f docker-compose.prod.yml down
    
    # Pull new images
    log "Pulling new Docker images"
    docker-compose -f docker-compose.prod.yml.new pull
    
    # Start new services
    log "Starting new services"
    docker-compose -f docker-compose.prod.yml.new up -d
    
    # Wait for services to be ready
    log "Waiting for services to be ready"
    sleep 30
    
    # Run database migrations
    log "Running database migrations"
    docker-compose -f docker-compose.prod.yml.new exec -T backend php artisan migrate --force
    
    # Clear caches
    log "Clearing application caches"
    docker-compose -f docker-compose.prod.yml.new exec -T backend php artisan config:cache
    docker-compose -f docker-compose.prod.yml.new exec -T backend php artisan route:cache
    docker-compose -f docker-compose.prod.yml.new exec -T backend php artisan view:cache
    
    # Replace old configuration
    mv docker-compose.prod.yml.new docker-compose.prod.yml
    
    success_msg "Deployment completed successfully"
}

# Health check
health_check() {
    log "Running health checks"
    
    local max_attempts=10
    local attempt=1
    
    while [ $attempt -le $max_attempts ]; do
        log "Health check attempt $attempt/$max_attempts"
        
        # Check backend health
        if curl -f -s "$APP_URL/health" > /dev/null; then
            success_msg "Backend health check passed"
            break
        else
            warning_msg "Backend health check failed, retrying in 30 seconds..."
            sleep 30
        fi
        
        attempt=$((attempt + 1))
    done
    
    if [ $attempt -gt $max_attempts ]; then
        error_exit "Health check failed after $max_attempts attempts"
    fi
}

# Cleanup old images
cleanup() {
    log "Cleaning up old Docker images"
    docker image prune -f
    docker volume prune -f
    success_msg "Cleanup completed"
}

# Rollback function
rollback() {
    log "Initiating rollback"
    
    # Get previous version
    local previous_version=$(git describe --tags --abbrev=0 HEAD~1 2>/dev/null || echo "v1.0.0")
    
    if [ "$previous_version" = "v1.0.0" ]; then
        error_exit "No previous version available for rollback"
    fi
    
    log "Rolling back to version: $previous_version"
    
    # Stop current services
    docker-compose -f docker-compose.prod.yml down
    
    # Update to previous version
    sed -i "s|yourusername/saas-foodtech-backend:.*|yourusername/saas-foodtech-backend:$previous_version|g" docker-compose.prod.yml
    sed -i "s|yourusername/saas-foodtech-frontend:.*|yourusername/saas-foodtech-frontend:$previous_version|g" docker-compose.prod.yml
    
    # Deploy previous version
    docker-compose -f docker-compose.prod.yml pull
    docker-compose -f docker-compose.prod.yml up -d
    
    # Wait and health check
    sleep 30
    health_check
    
    success_msg "Rollback to $previous_version completed"
}

# Notification function
send_notification() {
    local status=$1
    local message=$2
    
    if [ -n "$SLACK_WEBHOOK" ]; then
        curl -X POST -H 'Content-type: application/json' \
            --data "{\"text\":\"Deployment $status: $message\"}" \
            "$SLACK_WEBHOOK"
    fi
    
    log "Notification sent: $status - $message"
}

# Main deployment flow
main() {
    log "=== Starting Deployment Process ==="
    
    check_prerequisites
    
    case "${1:-deploy}" in
        "deploy")
            backup_current
            deploy_version
            health_check
            cleanup
            send_notification "SUCCESS" "Version $VERSION deployed successfully"
            ;;
        "rollback")
            rollback
            health_check
            send_notification "ROLLBACK" "Rolled back to previous version"
            ;;
        "health")
            health_check
            ;;
        *)
            echo "Usage: $0 [deploy|rollback|health] [version]"
            echo "  deploy  - Deploy new version (default: latest)"
            echo "  rollback - Rollback to previous version"
            echo "  health   - Run health check"
            exit 1
            ;;
    esac
    
    log "=== Deployment Process Completed ==="
}

# Script entry point
main "$@"
