# ✅ CHECKLIST EJECUTABLE DE REMEDIACIÓN

**Guía paso a paso para resolver todos los errores encontrados**

---

## 📌 INSTRUCCIONES GENERALES

Este checklist debe ejecutarse **en el orden listado**. Cada sección tiene:
- ✅ Tarea específica
- 🔗 Referencias a documentos de auditoría
- ⏱️ Tiempo estimado
- 🧪 Verificación

---

## FASE 1: CORRECCIONES ARQUITECTÓNICAS CRÍTICAS (Debe hacerse primero)

### 1.1 Crear Puertos en Domain Layer

**Tarea:** Crear interfaces Port para todas las dependencias de Infrastructure

**Archivos a crear:**

```bash
# En src/CatalogHarvest/Domain/Port/
touch HtmlParser.php
touch ActivityEmbedder.php
touch UserNotifier.php
```

**Verificación:**
```bash
php -l src/CatalogHarvest/Domain/Port/HtmlParser.php
php -l src/CatalogHarvest/Domain/Port/ActivityEmbedder.php
php -l src/CatalogHarvest/Domain/Port/UserNotifier.php
# Output esperado: "No syntax errors detected"
```

**Status:** ⏳ Pendiente

---

### 1.2 Crear Implementaciones de Puertos en Infrastructure

**Tarea:** Renombrar clases existentes e implementar nuevas interfaces

**Cambios:**

```bash
# Renombrar parser existente
mv src/CatalogHarvest/Infrastructure/Http/ActivityDetailParser.php \
   src/CatalogHarvest/Infrastructure/Http/XPathActivityDetailParser.php

# Editar clase para que implemente HtmlParser
# En XPathActivityDetailParser.php:
# Cambiar: class ActivityDetailParser
# Por:     class XPathActivityDetailParser implements HtmlParser

# Crear nueva clase para Embeddings
touch src/CatalogHarvest/Infrastructure/AI/EmbedderService.php

# Crear nueva clase para Notificaciones
touch src/Notifications/Infrastructure/FavoriteUserNotifier.php
```

**Verificación:**
```bash
php -l src/CatalogHarvest/Infrastructure/Http/XPathActivityDetailParser.php
grep "implements HtmlParser" src/CatalogHarvest/Infrastructure/Http/XPathActivityDetailParser.php
# Output esperado: línea encontrada
```

**Status:** ⏳ Pendiente

---

### 1.3 Refactorizar RefreshActivity Use-Case

**Tarea:** Eliminar dependencias de Infrastructure, usar solo Ports

**Cambios en:** `src/CatalogHarvest/Application/RefreshActivity/RefreshActivity.php`

```bash
# Reemplazar el archivo completo con la versión corregida de REMEDIATION_GUIDE.md
# Verificación:
grep "use CatalogHarvest\\\\Infrastructure\\\\Http\\\\ActivityDetailParser" src/CatalogHarvest/Application/RefreshActivity/RefreshActivity.php
# Output esperado: sin resultados (grep devuelve error code 1)

grep "use CatalogHarvest\\\\Domain\\\\Port\\\\HtmlParser" src/CatalogHarvest/Application/RefreshActivity/RefreshActivity.php
# Output esperado: línea encontrada
```

**Status:** ⏳ Pendiente

---

### 1.4 Refactorizar DiscoverActivities Use-Case

**Tarea:** Remover cualquier instanciación directa de clases Infrastructure

**Verificación:**
```bash
grep -n "new " src/CatalogHarvest/Application/DiscoverActivities/DiscoverActivities.php | grep -v "DateTimeImmutable\|RuntimeException"
# Output esperado: sin resultados (solo excepciones y objetos domain permitidos)
```

**Status:** ⏳ Pendiente

---

### 1.5 Centralizar Inyección de Dependencias

**Tarea:** Crear ContainerFactory y refactorizar index.php

**Archivos a crear:**

```bash
mkdir -p src/Shared/Infrastructure/DependencyInjection
touch src/Shared/Infrastructure/DependencyInjection/ContainerFactory.php
```

**Copiar código de REMEDIATION_GUIDE.md (sección 3️⃣)**

**Editar:** `apps/HttpApi/public/index.php`

```php
// Cambiar de:
$container = new \DI\Container();
$container->set(ActivityRepository::class, ...);
// ...

// A:
$container = ContainerFactory::create($_ENV['APP_ENV'] ?? 'development');
```

**Verificación:**
```bash
php apps/HttpApi/public/index.php
# (Debería cargar sin errores si se accede a /status vía curl)
```

**Status:** ⏳ Pendiente

---

## FASE 2: CORRECCIONES DE DESPLIEGUE (Cambia cómo se deployea)

### 2.1 Corregir Docker Compose - Volúmenes

**Tarea:** Cambiar estructura de volúmenes a `/data`

**Archivo:** `infra/compose.yaml`

```bash
# Reemplazar sección "volumes:" (líneas 104-106)
# Con versión corregida de REMEDIATION_GUIDE.md (sección 2️⃣)
```

**Verificación:**
```bash
grep "uned_data:" infra/compose.yaml
# Output esperado: 1 resultado

grep "/data/postgres" infra/compose.yaml
grep "/data/redis" infra/compose.yaml
# Output esperado: 2 resultados
```

**Status:** ⏳ Pendiente

---

### 2.2 Agregar Health Check Conditions

**Tarea:** Añadir `depends_on` con `condition: service_healthy`

**Archivo:** `infra/compose.yaml`

```bash
# Editar servicio "backend" y "harvester"
# Agregar antes de "networks:":
# depends_on:
#   db:
#     condition: service_healthy
#   redis:
#     condition: service_healthy
```

**Verificación:**
```bash
grep -A 2 "depends_on:" infra/compose.yaml | grep "service_healthy"
# Output esperado: 4 líneas encontradas (2 para backend, 2 para harvester)
```

**Status:** ⏳ Pendiente

---

### 2.3 Reescribir Dockerfile Backend con PHP-FPM

**Tarea:** Reemplazar entero Dockerfile backend

**Archivos:**

```bash
# Reemplazar:
rm infra/Dockerfile.backend
# Copiar versión de REMEDIATION_GUIDE.md (sección 5️⃣)

# Crear archivos de configuración:
mkdir -p infra/php
touch infra/php/php.ini
touch infra/php/www.conf
# Copiar contenido de REMEDIATION_GUIDE.md
```

**Verificación:**
```bash
grep "php-fpm" infra/Dockerfile.backend
# Output esperado: 2 líneas encontradas (FROM y CMD)

test -f infra/php/php.ini && echo "OK" || echo "MISSING"
test -f infra/php/www.conf && echo "OK" || echo "MISSING"
```

**Status:** ⏳ Pendiente

---

### 2.4 Mejorar Harvest Loop Script

**Tarea:** Añadir retry logic y logging mejorado

**Archivo:** `infra/scripts/harvest-loop.sh`

```bash
# Reemplazar con versión de REMEDIATION_GUIDE.md (sección 4️⃣)
```

**Verificación:**
```bash
grep "CURRENT_BACKOFF=" infra/scripts/harvest-loop.sh
# Output esperado: 2 líneas encontradas

grep "log_error\|log_warn" infra/scripts/harvest-loop.sh | wc -l
# Output esperado: >= 5
```

**Status:** ⏳ Pendiente

---

### 2.5 Validar VITE_API_URL en Dockerfile Frontend

**Tarea:** Validar variable de build en Dockerfile

**Archivo:** `infra/Dockerfile.frontend`

```bash
# Buscar ARG VITE_API_URL y agregar validación después:
# RUN if [ -z "$VITE_API_URL" ]; then \
#     echo "ERROR: VITE_API_URL must be set"; \
#     exit 1; \
#     fi
```

**Verificación:**
```bash
grep -A 3 "ARG VITE_API_URL" infra/Dockerfile.frontend | grep "ERROR"
# Output esperado: 1 línea encontrada
```

**Status:** ⏳ Pendiente

---

### 2.6 Mejorar Dockerfile Frontend con Labels

**Tarea:** Agregar metadata de imagen

**Archivo:** `infra/Dockerfile.frontend`

```bash
# Buscar línea "FROM nginx:1.25-alpine" y agregar después:
# LABEL maintainer="your-team@example.com"
# LABEL description="UNED Activities Finder Frontend"
# LABEL version="1.0.0"
```

**Verificación:**
```bash
grep "^LABEL" infra/Dockerfile.frontend | wc -l
# Output esperado: >= 3
```

**Status:** ⏳ Pendiente

---

## FASE 3: COMPLETAR MIGRACIONES Y SCRIPTS

### 3.1 Completar Init Scripts SQL

**Tarea:** Asegurar que todas las tablas se crean automáticamente

**Ubicación:** `infra/init-scripts/`

**Verificar que existen:**

```bash
ls -la infra/init-scripts/
# Output esperado:
# 00_create_auth_schema.sql
# 01_auth_enum_types.sql
# 02_create_app_schema.sql    ← DEBE EXISTIR
# 03_create_tables.sql         ← DEBE EXISTIR
```

**Crear archivo:** `infra/init-scripts/02_create_app_schema.sql`

```sql
-- Crear schema de aplicación
CREATE SCHEMA IF NOT EXISTS app;

-- Tablas de activities
CREATE TABLE app.activities (
  id UUID PRIMARY KEY,
  uned_id TEXT NOT NULL UNIQUE,
  url TEXT NOT NULL UNIQUE,
  title TEXT,
  description TEXT,
  start_date TIMESTAMP WITH TIME ZONE,
  end_date TIMESTAMP WITH TIME ZONE,
  modality VARCHAR(50),
  center VARCHAR(255),
  typology VARCHAR(255),
  area VARCHAR(255),
  price_amount INTEGER,      -- en centavos
  price_currency VARCHAR(3),
  is_free BOOLEAN DEFAULT false,
  enrollment_open BOOLEAN,
  enrollment_start_date TIMESTAMP WITH TIME ZONE,
  enrollment_end_date TIMESTAMP WITH TIME ZONE,
  enrollment_link TEXT,
  credits INTEGER,
  has_live BOOLEAN,
  has_recorded BOOLEAN,
  status VARCHAR(50) DEFAULT 'active',
  hash TEXT NOT NULL,
  created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
  updated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

CREATE INDEX idx_activities_uned_id ON app.activities(uned_id);
CREATE INDEX idx_activities_url ON app.activities(url);
CREATE INDEX idx_activities_status ON app.activities(status);
CREATE INDEX idx_activities_modality ON app.activities(modality);
CREATE INDEX idx_activities_center ON app.activities(center);

-- Tablas de usuarios
CREATE TABLE app.users (
  id UUID PRIMARY KEY,
  email TEXT NOT NULL UNIQUE,
  created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
  updated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

CREATE INDEX idx_users_email ON app.users(email);

-- Tabla de búsquedas guardadas
CREATE TABLE app.saved_searches (
  id UUID PRIMARY KEY,
  user_id UUID NOT NULL REFERENCES app.users(id),
  name TEXT NOT NULL,
  filters JSONB NOT NULL,
  created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
  updated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
  UNIQUE (user_id, name)
);

CREATE INDEX idx_saved_searches_user_id ON app.saved_searches(user_id);

-- Tabla de notificaciones
CREATE TABLE app.notifications (
  id UUID PRIMARY KEY,
  user_id UUID NOT NULL REFERENCES app.users(id),
  saved_search_id UUID REFERENCES app.saved_searches(id),
  title TEXT NOT NULL,
  message TEXT NOT NULL,
  activity_ids TEXT[],  -- Array de UUIDs
  read_at TIMESTAMP WITH TIME ZONE,
  delivered_at TIMESTAMP WITH TIME ZONE,
  created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

CREATE INDEX idx_notifications_user_id ON app.notifications(user_id);
CREATE INDEX idx_notifications_read ON app.notifications(read_at);
```

**Verificación:**
```bash
test -f infra/init-scripts/02_create_app_schema.sql && echo "OK" || echo "MISSING"
wc -l infra/init-scripts/*.sql
# Output esperado: varias tablas SQL
```

**Status:** ⏳ Pendiente

---

### 3.2 Validar Migraciones Reversibles

**Tarea:** Asegurar que cada `.up.sql` tiene `.down.sql`

**Verificación:**
```bash
cd infra/migrations
for up in *.up.sql; do
  down="${up%.up.sql}.down.sql"
  if [ ! -f "$down" ]; then
    echo "MISSING: $down"
  fi
done
# Output esperado: sin errores
```

**Status:** ⏳ Pendiente

---

## FASE 4: VALIDACIÓN DE CALIDAD

### 4.1 PHPStan Static Analysis

```bash
# Ejecutar análisis
composer phpstan

# Output esperado:
# ✓ [OK] No errors
```

**Status:** ⏳ Pendiente

---

### 4.2 Code Style Check

```bash
# Verificar estilo
composer cs-check

# Output esperado:
# ✓ [OK] 0 files with code style violations
```

**Status:** ⏳ Pendiente

---

### 4.3 Unit Tests

```bash
# Ejecutar tests
composer phpunit

# Output esperado:
# ✓ OK (X tests, X assertions)
```

**Status:** ⏳ Pendiente

---

### 4.4 Verificar Tamaño de Archivos

```bash
# Encontrar archivos > 300 líneas
find src apps -name "*.php" -exec wc -l {} \; | awk '$1 > 300 {print $2 " (" $1 " lines)"}'

# Output esperado:
# (sin resultados)
```

**Status:** ⏳ Pendiente

---

### 4.5 Verificar Violaciones de Dependencia

```bash
# Script personalizado (create if doesn't exist)
cat > check_dependencies.php << 'EOF'
<?php
$files = array_filter(
    explode("\n", shell_exec("find src -name 'Application/*.php' -type f")),
    'trim'
);

foreach ($files as $file) {
    $content = file_get_contents($file);
    if (preg_match('/use.*\\\\Infrastructure\\\\/', $content)) {
        echo "❌ VIOLATION: $file\n";
    }
}
echo "✓ Dependency check complete\n";
EOF

php check_dependencies.php
```

**Status:** ⏳ Pendiente

---

### 4.6 Tests de Integración

```bash
# Ejecutar tests de integración
composer phpunit --testsuite=Integration

# Output esperado:
# ✓ OK (X tests)
```

**Status:** ⏳ Pendiente

---

### 4.7 Frontend TypeScript Check

```bash
cd web
npm run typecheck

# Output esperado:
# ✓ 0 errors
```

**Status:** ⏳ Pendiente

---

### 4.8 Frontend Lint

```bash
cd web
npm run lint

# Output esperado:
# ✓ 0 errors
```

**Status:** ⏳ Pendiente

---

## FASE 5: DESPLIEGUE LOCAL

### 5.1 Limpiar Volúmenes Anteriores

```bash
cd infra
docker compose down -v

# Esperar a que se limpie
sleep 5
```

**Status:** ⏳ Pendiente

---

### 5.2 Construir Imágenes

```bash
cd infra
docker compose build --no-cache

# Output esperado:
# ✓ Successfully built 3 images
```

**Status:** ⏳ Pendiente

---

### 5.3 Levantar Servicios

```bash
cd infra
docker compose up -d

# Esperar 10 segundos
sleep 10

# Verificar estado
docker compose ps
# Output esperado: todos en "Up"
```

**Status:** ⏳ Pendiente

---

### 5.4 Verificar Logs

```bash
# Backend logs
docker compose logs backend | head -20

# Output esperado: sin errores PHP

# Harvester logs
docker compose logs harvester | head -10

# Output esperado: "[INFO] Starting harvest..."
```

**Status:** ⏳ Pendiente

---

### 5.5 Test de Conectividad

```bash
# Frontend
curl -s http://localhost:80 | grep -q "<!DOCTYPE html>" && echo "✓ Frontend OK"

# Backend API
curl -s http://localhost:8080/v1/activities | jq '.' && echo "✓ API OK"

# Health check
curl -s http://localhost:8080/status | jq '.' && echo "✓ Status OK"
```

**Status:** ⏳ Pendiente

---

### 5.6 Verificar Base de Datos

```bash
# Conectar a PostgreSQL
docker compose exec -T db psql -U postgres -d uned_activities -c "SELECT COUNT(*) FROM app.activities;"

# Output esperado: count = 0 (vacío al inicio)
```

**Status:** ⏳ Pendiente

---

## FASE 6: TESTS FUNCIONALES

### 6.1 Ejecutar Harvest

```bash
docker compose exec -T backend php apps/CliJobs/bin/discover.php --max-pages=2

# Output esperado:
# [INFO] Discovered X activities
```

**Status:** ⏳ Pendiente

---

### 6.2 Verificar Actividades en BD

```bash
docker compose exec -T db psql -U postgres -d uned_activities -c \
  "SELECT COUNT(*), AVG(CHAR_LENGTH(title)) FROM app.activities;"

# Output esperado: count > 0
```

**Status:** ⏳ Pendiente

---

### 6.3 Test Rate Limiting

```bash
# Hacer 100 requests rápidos
for i in {1..100}; do
  curl -s http://localhost:8080/v1/activities > /dev/null
done

# El 101 debería recibir 429 Too Many Requests
curl -v http://localhost:8080/v1/activities 2>&1 | grep -E "429|200"

# Output esperado: 429 (si rate limit activado)
```

**Status:** ⏳ Pendiente

---

### 6.4 Test Embeddings (si aplica)

```bash
docker compose exec -T backend php apps/CliJobs/bin/embeddings.php

# Output esperado:
# [INFO] Generated embeddings for X activities
```

**Status:** ⏳ Pendiente

---

## FASE 7: DOCUMENTACIÓN Y CIERRE

### 7.1 Actualizar README

```bash
# Editar README.md sección "Quick Start" con nuevas URLs de volúmenes
# Documentar cambios arquitectónicos
# Documentar cambios de despliegue
```

**Status:** ⏳ Pendiente

---

### 7.2 Crear Changelog

```markdown
# CHANGELOG.md

## [1.0.0] - 2026-02-04

### Security
- Fixed dependency inversion violations in Application layer
- Centralized DI configuration for better security

### Architecture
- Introduced Domain Ports for HTML parsing and embeddings
- Separated Infrastructure implementations from Application layer
- Implemented true Hexagonal Architecture

### Infrastructure
- Migrated from PHP built-in server to PHP-FPM + Nginx
- Centralized logs and data in /data volume for better backup strategy
- Added health checks with proper dependency conditions
- Implemented exponential backoff in harvest loop

### Bug Fixes
- Fixed volumes not respecting backup strategy
- Added validation for VITE_API_URL in Docker build
- Improved error handling and logging throughout
```

**Status:** ⏳ Pendiente

---

### 7.3 Crear Runbook de Despliegue

**Archivo:** `doc/runbooks/DEPLOYMENT.md`

```markdown
# Deployment Runbook

## Pre-deployment Checklist

- [ ] All PHPStan checks pass
- [ ] All tests pass
- [ ] No files exceed 300 lines
- [ ] No Architecture violations
- [ ] Volumes correctly configured

## Deployment Steps

1. Build images: `docker compose build`
2. Down previous services: `docker compose down`
3. Up new services: `docker compose up -d`
4. Verify: `curl http://localhost:8080/status`

## Rollback Procedure

...
```

**Status:** ⏳ Pendiente

---

## RESUMEN FINAL

**Total de tareas:** 35+  
**Tiempo estimado:** 48-56 horas  
**Prioridad:** MÁXIMA

### Hitos Principales:

1. ✅ FASE 1: Arquitectura (Bloqueador)
2. ✅ FASE 2: Despliegue (Operacional)
3. ✅ FASE 3: Migraciones (Data)
4. ✅ FASE 4: Validación (Quality)
5. ✅ FASE 5: Local Deploy (Smoke Tests)
6. ✅ FASE 6: Funcionalidad (E2E)
7. ✅ FASE 7: Cierre (Documentation)

---

**Status General:** 🔴 NO INICIADO

Marcar cada ✅ cuando se complete la tarea correspondiente.
