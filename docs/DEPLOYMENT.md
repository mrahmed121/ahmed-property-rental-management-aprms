# APRMS Deployment Guide

## Production Checklist

### Environment
- Set `APP_ENV=production` and `APP_DEBUG=false`. Never expose debug output in production.
- Generate a strong `APP_KEY` and `JWT_SECRET`. Never commit these.
- Use MySQL or PostgreSQL for production. SQLite is for development only.

### Backend
```bash
cd backend
composer install --no-dev --optimize-autoloader
cp .env.example .env
# Edit .env: APP_ENV=production, APP_DEBUG=false, DB_*, JWT_SECRET
php artisan key:generate
php artisan jwt:secret
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan storage:link
```

### Frontend
```bash
cd frontend
npm ci
# Set VITE_API_URL to the production API base
npm run build
# Serve dist/ via nginx or similar
```

### Web Server
- Point the document root to `backend/public/` for the API.
- Serve `frontend/dist/` as static files.
- Terminate TLS at the reverse proxy.

### Scheduled Tasks
Add to crontab for automated billing operations:
```
* * * * * cd /path/to/backend && php artisan schedule:run >> /dev/null 2>&1
```

### Backups
- Database: daily dumps, retained per your policy.
- `backend/storage/app/` (documents): included in backups; these are private and agency-scoped.

## Notes
- Concurrency protections use database row locks. They are verified on SQLite for correctness of logic; true parallel behavior should be verified on your production database engine before high-concurrency use.
- File uploads are validated by type and size (10MB) and stored in private agency-scoped storage, never in the public web root.
