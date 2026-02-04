# Production Deployment Runbook

## Prerequisites
- Docker and Docker Compose v2+
- Named volume `uned_data` exists
- Environment variables configured
- SSH access to production server

## Pre-Deployment Checks

```bash
# 1. Validate configuration
docker-compose -f infra/compose.yaml config

# 2. Run migrations
php infra/scripts/migrate.php up

# 3. Run tests
composer phpunit

# 4. Verify static analysis
composer phpstan
```

## Deployment

```bash
# 1. SSH into production server
ssh user@production-server

# 2. Navigate to project directory
cd /var/www/uned-activities

# 3. Pull latest code
git pull origin main

# 4. Install dependencies
composer install --no-dev --optimize-autoloader

# 5. Run migrations
php infra/scripts/migrate.php up

# 6. Rebuild containers
docker-compose -f infra/compose.yaml up -d --build

# 7. Verify health
curl http://localhost:8080/health

# 8. Monitor logs
docker-compose logs -f --tail=100
```

## Rollback (if needed)

```bash
# 1. Revert migrations
php infra/scripts/migrate.php down

# 2. Revert code
git revert HEAD

# 3. Rebuild containers
docker-compose -f infra/compose.yaml up -d --build
```

## Troubleshooting

### Container not starting
```bash
docker-compose logs backend
docker-compose ps
```

### Database connection issues
```bash
docker-compose exec db psql -U postgres -d uned_activities
```

### Clear Redis cache
```bash
docker-compose exec redis redis-cli FLUSHALL
```
