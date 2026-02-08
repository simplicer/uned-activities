# Deployment en Producción con Docker Swarm y Traefik

Este documento describe cómo desplegar la aplicación **UNED Activities Finder** en producción usando Docker Swarm y Traefik como reverse proxy.

## Arquitectura de Deployment

```
Internet → Traefik (HTTPS) → Docker Swarm
                              ├── Frontend (lexemas.com)
                              ├── API Backend (api.lexemas.com/v1/*)
                              ├── PostgreSQL
                              ├── Redis
                              └── Harvester (background jobs)
```

## Dominios

- **Frontend**: `lexemas.com` (+ `www.lexemas.com` redirect)
- **API**: `api.lexemas.com`
  - Endpoints: `/v1/status`, `/v1/version`, `/v1/activities`, etc.

## Prerrequisitos

1. **Docker Swarm** inicializado en el servidor:
   ```bash
   docker swarm init
   ```

2. **Traefik** desplegado como servicio global con:
   - Network `traefik-public` creada:
     ```bash
     docker network create --driver=overlay traefik-public
     ```
   - Certificados SSL automáticos con Let's Encrypt configurados
   - Entrypoints `web` (80) y `websecure` (443)

3. **Imagen Docker unificada** construida y publicada en tu registry:
   ```bash
   export VERSION=1.0.0
   export REGISTRY_HOST=registry.storage.simplicer.com
   export REGISTRY_NAMESPACE=antonio
   export VITE_API_URL=https://api.lexemas.com

   # App (backend + frontend estático en la misma imagen)
   docker build \
     -t ${REGISTRY_HOST}/${REGISTRY_NAMESPACE}/uned-backend:${VERSION} \
     --build-arg VITE_API_URL=${VITE_API_URL} \
     -f containers/Containerfile.backend .
   docker push ${REGISTRY_HOST}/${REGISTRY_NAMESPACE}/uned-backend:${VERSION}
   ```

## Paso 1: Crear Secrets

Ejecuta el script para crear los secrets de Docker Swarm:

```bash
bash infra/scripts/create-secrets.sh
```

El script te pedirá:
- Contraseña de PostgreSQL
- Contraseña de Redis
- Secreto JWT (mínimo 32 caracteres)
- Contraseña SMTP (opcional)

## Paso 2: Configurar Variables de Entorno

Crea un archivo `.env` en el directorio `infra/`:

```bash
# Versión de la aplicación
VERSION=1.0.0

# Contraseña de Redis (usa __SECRET__ para docker secrets)
REDIS_PASSWORD=changeme
```

El registry queda fijo en `infra/compose.stack.yml` como:
- `registry.storage.simplicer.com/antonio/uned-backend`

## Namespace del registry

En este stack, el namespace es `antonio` y forma parte del nombre completo de imagen:

```text
registry.storage.simplicer.com/antonio/uned-backend:1.0.0
```

En la mayoría de registries privados, el namespace/repo se crea automáticamente al primer `docker push`.

## Paso 3: Desplegar el Stack

```bash
cd infra
docker stack deploy -c compose.stack.yml uned-activities --with-registry-auth
```

## Verificación

### Ver servicios desplegados
```bash
docker stack services uned-activities
```

Deberías ver:
- `uned-activities_db` (1 réplica)
- `uned-activities_redis` (1 réplica)
- `uned-activities_backend` (3 réplicas)
- `uned-activities_harvester` (1 réplica)

### Ver logs
```bash
# Logs del backend
docker service logs -f uned-activities_backend

# Logs del harvester
docker service logs -f uned-activities_harvester

# Logs de la base de datos
docker service logs -f uned-activities_db
```

### Verificar endpoints

```bash
# Frontend
curl https://lexemas.com

# API Status
curl https://api.lexemas.com/v1/status

# API Version
curl https://api.lexemas.com/v1/version
```

## Actualización (Rolling Update)

Para actualizar a una nueva versión:

```bash
# 1. Construir y pushear nuevas imágenes
export VERSION=1.0.0
export REGISTRY_HOST=registry.storage.simplicer.com
export REGISTRY_NAMESPACE=antonio
export VITE_API_URL=https://api.lexemas.com

docker build \
  -t ${REGISTRY_HOST}/${REGISTRY_NAMESPACE}/uned-backend:$VERSION \
  --build-arg VITE_API_URL=${VITE_API_URL} \
  -f containers/Containerfile.backend .
docker push ${REGISTRY_HOST}/${REGISTRY_NAMESPACE}/uned-backend:$VERSION

# 2. Actualizar servicios
docker service update --with-registry-auth --image ${REGISTRY_HOST}/${REGISTRY_NAMESPACE}/uned-backend:$VERSION uned-activities_backend
```

El update se hará de forma gradual (rolling update) sin downtime.

## Escalado

```bash
# Escalar backend a 5 réplicas
docker service scale uned-activities_backend=5
```

## Rollback

Si algo sale mal, puedes hacer rollback:

```bash
docker service rollback uned-activities_backend
```

## Migraciones de Base de Datos

Las migraciones SQL se ejecutan automáticamente al crear el contenedor de PostgreSQL (gracias a `init-scripts/`).

Para migraciones posteriores:

```bash
# Ejecutar dentro del contenedor de backend
docker exec -it $(docker ps -q -f name=uned-activities_backend) \
  php infra/scripts/migrate.php up
```

## Monitoreo

### Métricas con Prometheus

Traefik expone métricas en `/metrics`. Configura Prometheus para scrapear:

```yaml
scrape_configs:
  - job_name: 'traefik'
    static_configs:
      - targets: ['traefik:8080']
```

### Health Checks

Los servicios tienen health checks configurados:
- PostgreSQL: `pg_isready`
- Redis: `redis-cli ping`
- Backend: Endpoint `/v1/status`

## Troubleshooting

### El servicio no arranca
```bash
# Ver eventos del servicio
docker service ps uned-activities_backend --no-trunc

# Ver logs detallados
docker service logs --tail 100 uned-activities_backend
```

### Problemas de conectividad
```bash
# Verificar networks
docker network ls
docker network inspect traefik-public
docker network inspect uned-activities_backend

# Verificar que los servicios están en la network correcta
docker service inspect uned-activities_backend --format '{{.Spec.TaskTemplate.Networks}}'
```

### Certificados SSL no se generan
```bash
# Verificar configuración de Traefik
docker service logs traefik

# Asegúrate de que los dominios apuntan a la IP del servidor
dig lexemas.com
dig api.lexemas.com
```

## Desinstalar

```bash
# Eliminar el stack completo
docker stack rm uned-activities

# Eliminar volumes (¡CUIDADO! Se pierden los datos)
docker volume rm uned-activities_postgres_data
docker volume rm uned-activities_redis_data

# Eliminar secrets
docker secret rm postgres_password redis_password jwt_secret smtp_password
```

## Configuración de Traefik (Dokploy)

Si usas **Dokploy**, el stack compose debe incluir las labels de Traefik como se muestra en `compose.stack.yml`.

Dokploy detectará automáticamente:
- Las labels de Traefik
- La red `traefik-public`
- Los dominios y certificados SSL

No necesitas configuración adicional, solo asegúrate de que:
1. Los dominios apuntan a tu servidor
2. El puerto 80 y 443 están abiertos en el firewall
3. Traefik está corriendo en Dokploy

## Seguridad

1. **Secrets**: Nunca commitees secrets al repositorio. Usa Docker Secrets.
2. **HTTPS**: Todos los endpoints usan HTTPS automáticamente con Let's Encrypt.
3. **Headers**: Security headers configurados (HSTS, XSS Protection, etc.)
4. **Rate Limiting**: Configurado en 100 req/min por IP
5. **CORS**: Solo permite orígenes específicos en producción

## Recursos

- Documentación Docker Swarm: https://docs.docker.com/engine/swarm/
- Documentación Traefik: https://doc.traefik.io/traefik/
- Dokploy: https://dokploy.com/
