# Operations Runbook

Guía de operaciones para el mantenimiento diario de UNED Activities Finder.

## Tareas Programadas (Cron)

### Job de Descubrimiento (Diario)

```bash
# Ejecutar discover job cada 6 horas
0 */6 * * * cd /app && php apps/CliJobs/bin/discover.php
```

### Job de Refresh (Diario)

```bash
# Ejecutar refresh job cada 2 horas
0 */2 * * * cd /app && php apps/CliJobs/bin/refresh.php
```

### Job de Digest (Horario)

```bash
# Ejecutar digest job a las 9:00 AM
0 9 * * * cd /app && php apps/CliJobs/bin/digest.php
```

## Troubleshooting

### Las actividades no se actualizan

1. Verificar que el discover job se está ejecutando:
   ```bash
   docker compose exec php cat /var/log/cron.log
   ```

2. Ejecutar manualmente:
   ```bash
   make discover
   ```

3. Verificar logs de errores:
   ```bash
   docker compose logs -f php
   ```

### Las notificaciones no se envían

1. Verificar que el digest job se ejecutó:
   ```bash
   make digest
   ```

2. Revisar tabla `notifications`:
   ```bash
   docker compose exec postgres psql -U postgres -d uned_activities \
     -c "SELECT * FROM notifications ORDER BY created_at DESC LIMIT 10;"
   ```

3. Verificar que saved_searches tiene `notify_on_new = true`

### Error de Rate Limiting

Si los usuarios reciben HTTP 429:

1. Aumentar límite en `.env`:
   ```bash
   RATE_LIMIT=200
   RATE_WINDOW=60
   ```

2. Reiniciar servicios:
   ```bash
   make infra-restart
   ```

### Base de Datos Lenta

1. Verificar conexiones activas:
   ```bash
   docker compose exec postgres psql -U postgres -d uned_activities \
     -c "SELECT count(*) FROM pg_stat_activity;"
   ```

2. Revisar queries lentos:
   ```bash
   docker compose exec postgres psql -U postgres -d uned_activities \
     -c "SELECT query, calls, total_time, mean_time FROM pg_stat_statements ORDER BY mean_time DESC LIMIT 10;"
   ```

3. Crear índices si es necesario:
   ```bash
   make migrate-status
   php infra/scripts/migrate.php up
   ```

## Escalado

### Aumentar Contenedores

Para escalar horizontalmente:

```yaml
# En docker-compose.yml
php:
  deploy:
    replicas: 3

nginx:
  deploy:
    replicas: 2
```

### Aumentar Recursos

```yaml
# En docker-compose.yml
services:
  php:
    deploy:
      resources:
        limits:
          cpus: '1.0'
          memory: 1G
        reservations:
          cpus: '0.5'
          memory: 512M
```

## Seguridad

### Rotación de Secretos

1. Cambiar contraseñas en `.env`
2. Rotar API tokens: agregar nuevo, eliminar viejo
3. Rotar `SUPABASE_ANON_KEY` desde el dashboard de Supabase
4. Recrear contenedores:
   ```bash
   make infra-restart
   ```

### Actualización de Dependencias

```bash
# PHP
composer update
make build-image

# Node
cd web && npm update
make build-image

# Reiniciar
make infra-restart
```

## Métricas Clave

Monitorear estos KPIs:

- **Número de actividades**: `SELECT COUNT(*) FROM activities;`
- **Actividades activas**: `SELECT COUNT(*) FROM activities WHERE status = 'active';`
- **Usuarios registrados**: `SELECT COUNT(*) FROM users;`
- **Búsquedas guardadas**: `SELECT COUNT(*) FROM saved_searches WHERE notify_on_new = true;`
- **Notificaciones enviadas**: `SELECT COUNT(*) FROM notifications WHERE created_at > NOW() - INTERVAL '7 days';`

## Contacto

Para issues de producción:

1. Revisar este runbook
2. Verificar logs en `make infra-logs`
3. Consultar `doc/architecture/AGENTS.md` para decisiones arquitectónicas
4. Crear issue en GitHub con etiqueta `production`
