# GitHub Secrets Setup Guide

## Required GitHub Secrets

Add the following secrets to your GitHub repository (Settings → Secrets and variables → Actions):

### Core Application Secrets
- **GEMINI_API_KEY**: `AIzaSyDWeHJB0JC3DJjXT-dBrPtIVy7lsRI4prk`

### Production Deployment Secrets
- **APP_KEY**: Your Laravel application key (run `php artisan key:generate --show`)
- **JWT_SECRET**: Your JWT secret key
- **APP_URL**: Production URL (e.g., `https://yourdomain.com`)
- **FRONTEND_URL**: Frontend URL (e.g., `https://yourdomain.com`)

### Database Secrets
- **DB_USERNAME**: Database username for production
- **DB_PASSWORD**: Database password for production

### Mail Configuration (Optional)
- **MAIL_HOST**: SMTP server hostname
- **MAIL_PORT**: SMTP server port
- **MAIL_USERNAME**: SMTP username
- **MAIL_PASSWORD**: SMTP password
- **MAIL_ENCRYPTION**: SMTP encryption (tls/ssl)
- **MAIL_FROM_ADDRESS**: From email address

### Reverb WebSocket Secrets
- **REVERB_APP_ID**: Reverb application ID
- **REVERB_APP_KEY**: Reverb application key
- **REVERB_APP_SECRET**: Reverb application secret

### Docker & Deployment Secrets
- **DOCKER_USERNAME**: Docker Hub username
- **DOCKER_PASSWORD**: Docker Hub password or access token
- **PROD_HOST**: Production server IP address
- **PROD_USER**: SSH username for production server
- **PROD_SSH_KEY**: Private SSH key for production server
- **PROD_PORT**: SSH port (default: 22)

### Notification Secrets (Optional)
- **SLACK_WEBHOOK**: Slack webhook URL for deployment notifications

## Quick Setup Commands

1. **Generate Laravel keys:**
```bash
php artisan key:generate --show
```

2. **Generate JWT secret:**
```bash
php artisan jwt:secret --show
```

3. **Add secrets to GitHub:**
   - Go to your repository
   - Settings → Secrets and variables → Actions
   - Click "New repository secret"
   - Add each secret from the list above

## Testing the Fix

After adding the secrets, push a commit to trigger the CI pipeline:

```bash
git add .
git commit -m "fix: Add Gemini API key to CI/CD workflows"
git push origin main
```

The workflows should now pass successfully with the Gemini AI integration working properly.
