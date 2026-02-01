# Deployment Runbook

Este documento describe los pasos para desplegar UNED Activities Finder en producción.

## Prerrequisitos

- Servidor con Ubuntu 22.04+ o similar
- Docker y Docker Compose instalados
- Dominio configurado con DNS apuntando al servidor
- SSL/TLS certificado (recomendado: Let's Encrypt)

## Variables de Entorno

Crear archivo `.env` con las siguientes variables:

```bash
# Aplicación
APP_DEBUG=false
APP_VERSION=1.0.0

# Base de datos (Supabase/PostgreSQL)
DB_HOST=postgres
DB_PORT=5432
DB_NAME=uned_activities
DB_USER=postgres
DB_PASSWORD=your_secure_password

# Supabase
SUPABASE_URL=https://your-project.supabase.co
SUPABASE_ANON_KEY=your_anon_key

# Redis (opcional, para rate limiting distribuido)
REDIS_HOST=redis
REDIS_PORT=6379
REDIS_PASSWORD=your_redis_password

# API
RATE_LIMIT=100
RATE_WINDOW=60
API_TOKENS=prod-token-1,prod-token-2

# Frontend
VITE_API_BASE=https://api.yourdomain.com
VITE_SUPABASE_URL=https://your-project.supabase.co
VITE_SUPABASE_ANON_KEY=your_anon_key
```

## Despliegue

### 1. Clonar Repositorio

```bash
git clone https://github.com/your-org/anvius-uned-extension-finder.git
cd anvius-uned-extension-finder
```

### 2. Construir Contenedores

```bash
make build
# O individualmente:
make build-backend
make build-frontend
```

### 3. Ejecutar Migraciones

```bash
make infra-up
docker compose up -d
make migrate
```

### 4. Verificar Servicios

```bash
# Verificar que servicios están corriendo
docker compose ps

# Verificar API
curl https://api.yourdomain.com/status

# Verificar frontend
curl https://www.yourdomain.com
```

## Despliegue Continuo

### Configurar CI/CD (GitHub Actions)

El workflow `.github/workflows/deploy.yml` se ejecutará en cada push a `main`.

Para despliegue manual:

```bash
# Actualizar código
git pull origin main

# Reconstruir contenedores
make build

# Reiniciar servicios
make infra-restart
```

## Rollback

Si algo sale mal:

```bash
# Revertir a versión anterior
git revert HEAD
git push

# O reset a commit específico
git reset --hard <commit-hash>
git push --force
```

## Monitoreo

### Ver Logs

```bash
# Logs de todos los servicios
make infra-logs

# Logs de servicio específico
docker compose logs -f php
docker compose logs -f postgres
```

### Health Checks

```bash
# Status de API
curl https://api.yourdomain.com/status

# Versión
curl https://api.yourdomain.com/version
```

## Backup

Los datos persistentes están en `/data` para rsnapshot.

Para backup manual:

```bash
# Dump de base de datos
docker compose exec postgres pg_dump -U postgres uned_activities > backup.sql

# Restaurar
docker compose exec -T postgres psql -U postgres uned_activities < backup.sql
```
