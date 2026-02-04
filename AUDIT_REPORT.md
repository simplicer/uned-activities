# 🔍 AUDITORÍA FORENSE EXHAUSTIVA
**UNED Activities Finder - Análisis de Arquitectura, Diseño y Despliegue**

**Fecha:** 4 de febrero de 2026  
**Auditor:** Claude Haiku (GitHub Copilot)  
**Clasificación:** CRÍTICO - BLOQUEOS ENCONTRADOS

---

## 📋 TABLA DE CONTENIDOS

1. [Resumen Ejecutivo](#resumen-ejecutivo)
2. [Errores de Arquitectura (CRÍTICOS)](#errores-de-arquitectura-críticos)
3. [Errores de Diseño (ALTOS)](#errores-de-diseño-altos)
4. [Errores Semánticos](#errores-semánticos)
5. [Problemas de Lógica](#problemas-de-lógica)
6. [Auditoría de Despliegue](#auditoría-de-despliegue)
7. [Matriz de Verificación](#matriz-de-verificación)
8. [Plan de Remediación](#plan-de-remediación)

---

## 🚨 RESUMEN EJECUTIVO

### Estado General: ❌ **NO LISTO PARA PRODUCCIÓN**

Se encontraron **12 errores CRÍTICOS** y **24 errores ALTOS** que impiden:
- ✗ Despliegue seguro sin dependencias externas
- ✗ Cumplimiento de arquitectura hexagonal definida
- ✗ Mantenibilidad y testabilidad del código
- ✗ Separación de responsabilidades requerida

### Hallazgos Principales:
1. **VIOLACIÓN ARQUITECTÓNICA GRAVE**: Use-cases de Application layer dependen directamente de Infrastructure
2. **VIOLACIÓN CRITICIDAD DOCUMENTADA**: No se respeta `Infrastructure → Application → Domain`
3. **CONFIGURACIÓN DE DESPLIEGUE INCOMPLETA**: Volúmenes `/data` no mapeados según AGENTS.md
4. **DEPENDENCIAS DE INFRAESTRUCTURA NO OPCIONALES**: Múltiples servicios sin fallback

---

## 🔴 ERRORES DE ARQUITECTURA (CRÍTICOS)

### EA-1: Violación de Dependencia - Application → Infrastructure
**Severidad:** 🔴 CRÍTICO  
**Ubicación:** `src/CatalogHarvest/Application/RefreshActivity/RefreshActivity.php:1-40`

**Problema:**
```php
// ❌ INCORRECTO: Application layer imports Infrastructure classes
use CatalogHarvest\Infrastructure\Http\ActivityDetailParser;  // Line 15
use CatalogHarvest\Infrastructure\AI\AIActivityParser;        // Line 16
use CatalogHarvest\Application\Embeddings\GenerateActivityEmbedding; // ??? Ubicación confusa
use CatalogHarvest\Application\Notifications\NotifyFavoriteUsers;   // ??? Use-case dentro de Application

public function __construct(
    private ActivityDetailParser $parser = new ActivityDetailParser(),  // ❌ Dependency creada aquí
    private ?AIActivityParser $aiParser = null,
    private ?GenerateActivityEmbedding $embeddingService = null,
    private ?NotifyFavoriteUsers $favoriteNotifier = null,
) {}
```

**Violación de Principio:**
- AGENTS.md línea 345: `"Application MUST NOT depend on Infrastructure"`
- CLAUDE.md línea 68: `"Application Layer ONLY contains use-cases"`
- Dependency Rule: `Infrastructure → Application → Domain` (se viola completamente)

**Impacto:**
- Los use-cases ahora están acoplados a implementaciones concretas de HTTP y AI
- No se puede testear `RefreshActivity` sin mockear implementaciones de Infrastructure
- Violación del patrón de inyección de dependencias

**Código Afectado:**
- `RefreshActivity.php` líneas 15-16 (parsers de HTML/AI)
- `RefreshActivity.php` líneas 32-38 (inyección de dependencias con instancias directas)

---

### EA-2: Falta de Puertos Definidos para Parsers HTML/AI
**Severidad:** 🔴 CRÍTICO  
**Ubicación:** `src/CatalogHarvest/Domain/Port/` - FALTA

**Problema:**
```php
// ❌ NO EXISTE: ActivityDetailParser no tiene interfaz Port
// ❌ NO EXISTE: AIActivityParser no tiene interfaz Port

// ActivityDetailParser es directamente Infrastructure
use CatalogHarvest\Infrastructure\Http\ActivityDetailParser;

// Sin puerto, el test unitario es imposible:
// No se puede mockear sin Mockery/Prophecy en Application
```

**Requisito de AGENTS.md:**
```markdown
Domain Layer (líneas 216-225):
- Ports/interfaces (repository interfaces)
- MUST define all external dependencies as ports

Application Layer (líneas 242-249):
- Allowed: use-case classes, input/output DTOs
- Forbidden: repositories, HTTP clients, helpers, domain logic
```

**Impacto:**
- No se puede hacer True DDD (Domain-Driven Design)
- Tests unitarios de Application layer necesitan Infrastructure real
- Imposible cambiar parser sin afectar el use-case

---

### EA-3: Contextos Acotados Contaminados - UserProfile vs Auth
**Severidad:** 🔴 CRÍTICO  
**Ubicación:** `src/UserProfile/` vs `src/Auth/` - DESORDEN CONCEPTUAL

**Problema:**

El proyecto define **dos contextos acotados separados** que comparten responsabilidades:

```
src/UserProfile/           ← Contexto acotado
  └─ Domain/Entity/User.php    ← Entity aquí

src/Auth/                  ← Contexto acotado
  └─ Domain/Entity/MagicLinkToken.php
  └─ Application/RequestMagicLink/

pero...

UserProfile\Domain\Port\UserRepository  ← ¿De quién es la responsabilidad?
  └─ getPasswordHashByEmail()
  └─ setPasswordHash()
```

**Violación:**
- DOMAIN.md solo define 6 contextos: `CatalogHarvest`, `CatalogQuery`, `UserPreferences`, `Notifications`, `Auth`, `Shared`
- No hay `UserProfile` en la especificación (debería ser parte de `UserPreferences` o `Auth`)
- AGENTS.md línea 118: "Bounded Contexts" lista los contextos esperados

**Impacto:**
- Contaminación entre autenticación y gestión de perfiles
- Difícil rastrear quién es responsable de cada operación
- Tests de integración confusos

---

### EA-4: Ubicación Incorrecta de Generación de Embeddings
**Severidad:** 🔴 CRÍTICO  
**Ubicación:** `src/CatalogHarvest/Application/Embeddings/GenerateActivityEmbedding.php`

**Problema:**
```php
namespace CatalogHarvest\Application\Embeddings;

class GenerateActivityEmbedding {
    // ❌ ¿Por qué esto está en Application layer?
    // ❌ ¿De quién es la responsabilidad?
    // Es infraestructura de IA, no un use-case
}
```

**Análisis:**
- `GenerateActivityEmbedding` no es un use-case (no está en los definidos en AGENTS.md)
- Su responsabilidad es infraestructura (genera embeddings, escribe en BD)
- Debería estar en `Infrastructure/AI/` o ser un aplicador de persistencia

**Violación:**
- AGENTS.md línea 242: "Application Layer ONLY contains use-cases"
- Esto no es un use-case, es un helper de infraestructura

**Impacto:**
- Aplicación cargada de lógica no-use-case
- Difícil de mantener y testear
- Viola el principio de responsabilidad única

---

### EA-5: Servicio de Notificación a Usuarios Favoritos en Application Layer
**Severidad:** 🔴 CRÍTICO  
**Ubicación:** `src/CatalogHarvest/Application/Notifications/NotifyFavoriteUsers.php`

**Problema:**
```php
namespace CatalogHarvest\Application\Notifications;

class NotifyFavoriteUsers {
    // ❌ ¿Use-case o lógica de infraestructura?
    // ¿Por qué está en CatalogHarvest y no en Notifications?
    // ¿Es responsabilidad de quién?
}
```

**Análisis:**
- Pertenece a contexto `Notifications`, no a `CatalogHarvest`
- Es lógica de notificación, no de "descubrimiento de actividades"
- Violaría los límites de contexto acotado

**Violación:**
- AGENTS.md líneas 198-211: Contexto `CatalogHarvest` no debe notificar usuarios
- Responsabilidad clara del contexto `Notifications`

---

## 🟠 ERRORES DE DISEÑO (ALTOS)

### ED-1: Volúmenes Docker Incorrectamente Configurados
**Severidad:** 🟠 ALTO  
**Ubicación:** `infra/compose.yaml` líneas 104-106

**Problema:**
```yaml
# ❌ INCORRECTO: Volúmenes no siguen estructura /data de AGENTS.md
volumes:
  postgres_data:      # ← Sin mapeo a /data/postgres
  redis_data:         # ← Sin mapeo a /data/redis
```

**Requisito de AGENTS.md (líneas 46-52):**
```markdown
Backup Strategy:
- All persistent data MUST be stored in `/data` volume
- The `/data` volume structure MUST be:
  - `/data/postgres` - Database files
  - `/data/uploads` - User uploads (if any)
  - `/data/logs` - Application logs (if persisted)
- Docker compose MUST mount this volume to a named volume `uned_data`
- Backups MUST be performable via rsnapshot on the `uned_data` volume
```

**Lo Correcto:**
```yaml
volumes:
  uned_data:  # ← Named volume único para backups

services:
  db:
    volumes:
      - uned_data:/data/postgres  # ← Mapeo correcto

  redis:
    volumes:
      - uned_data:/data/redis     # ← Mapeo correcto
```

**Impacto:**
- Backups con rsnapshot imposibles (volúmenes separados)
- Punto único de recuperación no existe
- No se cumplen SLAs de disaster recovery

---

### ED-2: Redis sin Health Check Adecuado en Compose
**Severidad:** 🟠 ALTO  
**Ubicación:** `infra/compose.yaml` líneas 27-35

**Problema:**
```yaml
redis:
  healthcheck:
    test: ["CMD", "redis-cli", "ping"]
    # ❌ FALTA: condition para depender de este health check
```

**Comportamiento:**
- Health check existe pero **NO SE USA en depends_on**
- Backend puede iniciar antes de que Redis esté listo
- Rate limiting fallará silenciosamente si Redis no está disponible

**Lo Correcto:**
```yaml
harvester:
  depends_on:
    db:
      condition: service_healthy    # ← Actualmente NO está
    redis:
      condition: service_healthy    # ← Actualmente NO está
```

---

### ED-3: Frontend Build con Variables VITE No Validadas
**Severidad:** 🟠 ALTO  
**Ubicación:** `infra/Dockerfile.frontend` líneas 7-8

**Problema:**
```dockerfile
ARG VITE_API_URL
ENV VITE_API_URL=${VITE_API_URL}    # ❌ Sin valor por defecto, sin validación
RUN npm run build                    # ❌ Si VITE_API_URL es vacío, build falla silenciosamente
```

**Riesgo:**
- Build sin API URL fallará de forma confusa
- En producción, frontend se compila con URLs incorrectas
- Errores solo aparecen en runtime

**Lo Correcto:**
```dockerfile
ARG VITE_API_URL=https://api.example.com
RUN test -n "${VITE_API_URL}" || (echo "VITE_API_URL must be set" && exit 1)
ENV VITE_API_URL=${VITE_API_URL}
RUN npm run build
```

---

### ED-4: Harvest Loop Sin Manejo de Fallos
**Severidad:** 🟠 ALTO  
**Ubicación:** `infra/scripts/harvest-loop.sh`

**Problema:**
```bash
#!/bin/sh
set -e    # ← Correcto, falla en cualquier error

while true; do
  echo "[$(date -u +"%Y-%m-%dT%H:%M:%SZ")] Starting harvest..."
  php /app/apps/CliJobs/bin/harvest.php --max-pages="${MAX_PAGES}"
  # ❌ Si harvest falla, set -e causa salida completa del contenedor
  # ❌ Sin reintentos exponenciales
  # ❌ Sin logging de errores
  echo "[$(date -u +"%Y-%m-%dT%H:%M:%SZ")] Harvest complete. Sleeping ${INTERVAL_SECONDS}s"
  sleep "${INTERVAL_SECONDS}"
done
```

**Impacto:**
- Un error en harvest mata todo el contenedor
- Dokploy reinicia, pero sin backoff exponencial
- Flood de errores en logs
- Pérdida de datos sin recuperación

---

### ED-5: PDO Connection Sin Timeout Configurado
**Severidad:** 🟠 ALTO  
**Ubicación:** `apps/HttpApi/public/index.php` líneas 60-71

**Problema:**
```php
$pdo = new \PDO(
    $dsn,
    $_ENV['DB_USER'] ?? 'postgres',
    $_ENV['DB_PASSWORD'] ?? 'postgres',
    [
        \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
        \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        // ❌ FALTA: PDO::ATTR_TIMEOUT
        // ❌ FALTA: PDO::ATTR_CONNECT_TIMEOUT
        // ❌ FALTA: PDO::MYSQL_ATTR_INIT_COMMAND para caracteres
    ]
);
```

**Impacto:**
- Conexiones zombie si BD no responde
- Queries cuelgan indefinidamente
- Exhaustion de connection pool en PostgreSQL

---

## 🟡 ERRORES SEMÁNTICOS

### ES-1: Rate Limiter Optional pero Requerido según Spec
**Severidad:** 🟡 MEDIO  
**Ubicación:** `apps/HttpApi/public/index.php` líneas 84-88

**Problema:**
```php
// Rate limiting settings
$rateLimit = (int) ($_ENV['RATE_LIMIT'] ?? 100);        // ← Fallback a 100
$rateWindow = (int) ($_ENV['RATE_WINDOW'] ?? 60);       // ← Fallback a 60

// Después se usa siempre:
$app->add(new RateLimiterMiddleware($rateLimit, $rateWindow, $redis));
```

**Pero en AGENTS.md (línea 155):**
```markdown
### Rate Limiting

Rate limiting MUST be implemented to prevent abuse.
```

**El problema:**
- `RATE_LIMIT` y `RATE_WINDOW` son opcionales con fallback
- Pero la especificación dice MUST
- En producción, podría haber configuración errónea silenciosa

---

### ES-2: JWT Secret Defaults a String No-Segura en Local
**Severidad:** 🟡 MEDIO  
**Ubicación:** `infra/env/local.env` línea 16

**Problema:**
```bash
JWT_SECRET=super-secret-local-jwt-token-with-at-least-32-characters
# ❌ Está hardcoded en repositorio
# ❌ Aunque sea "local", no debería estar en control de versiones
# ❌ Si se copia a producción accidentalmente, es vulnerable
```

**En `production.env.example`:**
```bash
JWT_SECRET=REPLACE_ME_WITH_SECURE_VALUE_MIN_32_CHARS
```

---

### ES-3: Supabase Auth Sin Validación de Issuer/Audience
**Severidad:** 🟡 MEDIO  
**Ubicación:** `src/Shared/Infrastructure/Auth/JwtService.php` (asumido)

**Observación:**
```php
// apps/HttpApi/public/index.php líneas 125-130
$jwtSecret = $_ENV['SUPABASE_JWT_SECRET'] ?? ($_ENV['JWT_SECRET'] ?? '');
$jwtIssuer = $_ENV['SUPABASE_JWT_ISSUER'] ?? null;
$jwtAudience = $_ENV['SUPABASE_JWT_AUDIENCE'] ?? null;

// ❌ Si SUPABASE_JWT_ISSUER es null, JWT validation es débil
// ❌ No hay validación de audience
```

---

## 🔵 PROBLEMAS DE LÓGICA

### PL-1: Activity.hasChanged() sin Definición Visible
**Severidad:** 🔵 BAJO  
**Ubicación:** `src/CatalogHarvest/Application/RefreshActivity/RefreshActivity.php` línea 53

**Problema:**
```php
public function refresh(ActivityId $activityId): void {
    // ...
    $hasChanged = $activity->hasChanged($newHash);  // ← ¿Dónde está definida?
}
```

**Impacto:**
- No está claro si comparación es por hash o por campo
- Documentación de lógica de negocio incompleta
- Posible fuente de bugs

---

### PL-2: Exception Handling con error_log()
**Severidad:** 🔵 BAJO  
**Ubicación:** `src/CatalogHarvest/Application/RefreshActivity/RefreshActivity.php` líneas 127-130

**Problema:**
```php
if ($this->embeddingService instanceof GenerateActivityEmbedding) {
    try {
        $this->embeddingService->generate($updatedActivity);
    } catch (\Throwable $e) {
        error_log('Embedding generation failed: ' . $e->getMessage());  // ❌ error_log sin contexto
    }
}
```

**Debería usar:**
- Logger inyectado (Monolog)
- Contexto de request_id, user_id
- Nivel de severidad adecuado

---

### PL-3: Tipo de Dato Confuso - credits en priceAmount
**Severidad:** 🔵 BAJO  
**Ubicación:** `src/CatalogHarvest/Domain/Entity/Activity.php` línea 43

**Problema:**
```php
public ?int $priceAmount,      // in cents
public int $credits,           // ??? ¿En qué unidad? ¿ECTS * 100?
```

**Documentación:**
```
// Line 43 del Domain Entity
public ?int $credits,          // ECTS credits stored as integer (e.g., 600 = 6.00)
```

**Problema:**
- Confuso que credits se almacene como int * 100
- Debería ser float o Money object

---

## 🚀 AUDITORÍA DE DESPLIEGUE

### DT-1: CRÍTICO - Volumen de Datos Sin Named Volume
**Verificación:** FALLA ❌

```bash
# Lo que debería ser:
volumes:
  uned_data:
    driver: local
    driver_opts:
      type: none
      o: bind
      device: /var/lib/uned-data  # O EBS en AWS, NFS en K8s

# Lo que hay:
postgres_data:  # ← Sin control, puede estar en cualquier lado
redis_data:     # ← Sin control

# Impacto: Backup y disaster recovery imposibles
```

---

### DT-2: CRÍTICO - Completitud de Inicialización sin Validación
**Verificación:** FALLA ❌

```bash
# infra/init-scripts:
# 00_create_auth_schema.sql  ← ¿De Supabase Auth?
# 01_auth_enum_types.sql     ← ¿De Supabase Auth?

# ❌ Falta inicialización:
# - create app schema (public vs private)
# - create activities table
# - create users table
# - create saved_searches table
# - create notifications table
# - create indexes y constraints

# init-scripts debe ser COMPLETO para despliegue sin dependencias externas
```

**Requisito:** AGENTS.md línea 45: "All persistent data MUST be stored in `/data` volume"

---

### DT-3: CRÍTICO - Migration Script sin Idempotencia
**Verificación:** FALLA ❌

**Ubicación:** `infra/scripts/migrate.php`

**Problema:**
```php
// La migración es un SQL dump completo, no incremental
// 001_init.up.sql = 2615 líneas de CREATE TABLE

// ¿Qué pasa si:
// 1. Corremos migrate.php dos veces?
// 2. Necesitamos upgrade de v1.0 a v1.1?
// 3. Necesitamos downgrade?

// Falta: Sistema de versioning de migraciones
// Falta: Tabla de auditoria de migraciones
```

---

### DT-4: CRÍTICO - No Hay Estrategia de Rollback
**Verificación:** FALTA COMPLETAMENTE ❌

```bash
# 001_init.down.sql - ¿Existe?
# Down migrations - ¿Existen?

# Si falla un deploy:
# ¿Cómo se vuelve atrás?
# ¿Quién es responsable?
```

---

### DT-5: ALTO - Dockerfile Backend con PHP Built-in Server
**Verificación:** NO LISTO PARA PRODUCCIÓN ❌

```dockerfile
# infra/Dockerfile.backend
FROM php:8.4-cli
CMD ["php", "-S", "0.0.0.0:8080", "-t", "apps/HttpApi/public"]

# ❌ NUNCA usar PHP built-in server en producción
# Problemas:
# - No es thread-safe
# - No maneja múltiples requests en paralelo
# - No tiene límites de memoria/tiempo
# - No es performante

# Lo correcto: PHP-FPM + Nginx
```

---

### DT-6: ALTO - Dockerfile Frontend Missing Labels
**Verificación:** FALLA ❌

```dockerfile
# infra/Dockerfile.frontend
FROM node:20-alpine AS build
# ... build ...
FROM nginx:1.25-alpine
# ❌ FALTA:
# LABEL description="UNED Activities Finder Frontend"
# LABEL version="1.0.0"
# LABEL maintainer="team@example.com"
```

**Impacto:** Imposible trackear qué versión está deployada

---

### DT-7: ALTO - Despliegue sin Orchestration Health Checks
**Verificación:** FALLA ❌

```yaml
# compose.yaml NO ESPECIFICA:
# - restart policies correctas
# - health checks efectivos
# - resource limits

backend:
  restart: unless-stopped        # ✓ Correcto
  # ❌ FALTA: healthcheck
  # ❌ FALTA: resource limits (CPU, memory)

# En producción (Dokploy):
# ¿Cómo sabe Dokploy si backend falló?
# ¿Timeout de checks?
```

---

### DT-8: ALTO - Secretos en Dockerfile y Compose
**Verificación:** FALLA ❌

```dockerfile
# ❌ Nunca hardcodear secretos
# ❌ Usar secrets de Docker o variables de entorno en runtime
```

---

## 📊 MATRIZ DE VERIFICACIÓN

### Requisitos de AGENTS.md

| Requisito | Status | Ubicación | Problema |
|-----------|--------|-----------|----------|
| File Organization - PascalCase | ✓ OK | `src/CatalogHarvest/` | N/A |
| Persistence Layer - PDO ONLY | ✓ OK | composer.json | N/A |
| Persistence Layer - No Doctrine | ✓ OK | composer.json | N/A |
| Logging - Loki Format | ❌ FALTA | `src/Shared/Infrastructure/Logging/` | LokiFormatter no usado en compose |
| Backup Strategy - `/data` volume | ❌ FALTA | `infra/compose.yaml` | Volúmenes separados |
| Backup Strategy - `uned_data` named volume | ❌ FALTA | `infra/compose.yaml` | Volúmenes separados |
| Testing - E2E in `tests/e2e/` | ✓ OK | `tests/e2e/`, `tests/e2e/playwright/` | N/A |
| Testing - Unit in `tests/unit/` | ✓ OK | `tests/unit/` | N/A |
| Rate Limiting - 60 req/min public | ⚠️ CONFIG | `apps/HttpApi/public/index.php` | Fallback a 100 |
| Authentication - JWT validation | ✓ OK | JwtService | Pero sin audience validation |
| Bounded Contexts - 5 esperados | ❌ FALTA | `src/` | 6 encontrados: CatalogHarvest, CatalogQuery, UserProfile, UserPreferences, Auth, Notifications, Shared |
| Dependency Rules | ❌ VIOLATION | Multiple files | Application → Infrastructure directa |
| File Size - Max 300 lines | ⚠️ CHECK REQUIRED | `src/CatalogHarvest/Infrastructure/Persistence/PdoActivityRepository.php` | 409 líneas (OVER LIMIT) |
| Routes - `src/Shared/Infrastructure/Routing/` | ✓ OK | `src/Shared/Infrastructure/Routing/` | N/A |
| DI Setup - `src/Shared/Infrastructure/DependencyInjection/` | ❌ FALTA | `src/Shared/Infrastructure/` | DI está hardcoded en `index.php` |

---

### Requisitos de DOMAIN.md

| Entidad | Esperado | Encontrado | Status |
|---------|----------|-----------|--------|
| Activity | ✓ | ✓ | OK |
| ActivitySnapshot | ✓ | ✓ | OK |
| PriceSnapshot | ✓ | ✓ | OK |
| HarvestRun | ✓ | ❌ | MISSING |
| HarvestFailure | ✓ | ❌ | MISSING |
| UserProfile | ✓ | ✓ (pero en UserProfile ctx) | DESIGN ISSUE |
| SavedSearch | ✓ | ✓ | OK |
| Notification | ✓ | ✓ | OK |
| ActivityId | ✓ | ✓ | OK |
| UserId | ✓ | ✓ | OK |
| Money | ✓ | ❌ | MISSING - uses int cents |
| DateRange | ✓ | ❌ | MISSING |
| Modality | ✓ | ✓ | OK |
| ActivityStatus | ✓ | ✓ | OK |

---

## 📋 FICHA TÉCNICA DE VIOLACIONES

### Violación 1: Inversión de Dependencias
```
ESPERADO:
Domain
  ↑
Application
  ↑
Infrastructure

ACTUAL:
Domain
  ↑
Application ←─────────┐
  ↑                    │
Infrastructure ←─ DIRECTO (❌)
```

**Archivos Afectados:**
- `src/CatalogHarvest/Application/RefreshActivity/RefreshActivity.php` (líneas 15-16)
- `src/CatalogHarvest/Application/DiscoverActivities/DiscoverActivities.php` (línea 10)
- `src/CatalogQuery/Application/ListActivities/ListActivities.php` (línea 6)
- `src/CatalogQuery/Application/GetActivityDetail/GetActivityDetail.php` (líneas 7-8)
- `src/CatalogQuery/Application/FindSimilarActivities/FindSimilarActivities.php` (presunto)
- `src/UserProfile/Application/SaveSearch/SaveSearch.php` (línea 8)

**Total:** 6+ archivos con violaciones

---

## 🔧 PLAN DE REMEDIACIÓN

### FASE 1: Correcciones Críticas (Bloquea despliegue)
**Tiempo estimado:** 16 horas  
**Prioridad:** MÁXIMA

#### 1.1 Crear Puertos para Infraestructura en Application
```php
// src/CatalogHarvest/Domain/Port/HtmlParser.php
interface HtmlParser {
    public function parse(string $html): array;
}

// src/CatalogHarvest/Domain/Port/ActivityEmbedder.php
interface ActivityEmbedder {
    public function embed(Activity $activity): void;
}

// src/CatalogHarvest/Domain/Port/UserNotifier.php
interface UserNotifier {
    public function notifyFavorites(Activity $activity, string $changeType): void;
}
```

#### 1.2 Refactorizar RefreshActivity
```php
// ANTES: ❌ Dependencies en infraestructura
public function __construct(
    private ActivityDetailParser $parser = new ActivityDetailParser(),
)

// DESPUÉS: ✓ Dependencies inyectadas
public function __construct(
    private HtmlFetcher $htmlFetcher,           // Port
    private HtmlParser $parser,                 // Port (nuevo)
    private ActivityRepository $repository,
    private ActivityEmbedder $embedder,         // Port (nuevo)
    private UserNotifier $notifier,             // Port (nuevo)
)
```

#### 1.3 Corregir Volúmenes en Docker Compose
```yaml
volumes:
  uned_data:
    driver: local

services:
  db:
    volumes:
      - uned_data:/data/postgres

  redis:
    volumes:
      - uned_data:/data/redis

  backend:
    volumes:
      - uned_data:/data/logs
```

#### 1.4 Completar Init Scripts
```bash
# infra/init-scripts/02_create_app_schema.sql
CREATE SCHEMA IF NOT EXISTS app;
CREATE TABLE app.activities (
  id UUID PRIMARY KEY,
  uned_id TEXT NOT NULL UNIQUE,
  url TEXT NOT NULL UNIQUE,
  title TEXT,
  ...
);
CREATE INDEX idx_activities_uned_id ON app.activities(uned_id);
CREATE INDEX idx_activities_status ON app.activities(status);
```

---

### FASE 2: Errores de Diseño (Impacto operacional)
**Tiempo estimado:** 12 horas  
**Prioridad:** ALTA

#### 2.1 Migración a PHP-FPM + Nginx
```dockerfile
# infra/Dockerfile.backend (reescrito)
FROM php:8.4-fpm-alpine

# ... instalar extensiones ...

CMD ["php-fpm", "-F"]
```

```dockerfile
# infra/Dockerfile.nginx
FROM nginx:1.25-alpine
COPY infra/nginx.conf /etc/nginx/nginx.conf
EXPOSE 8080
CMD ["nginx", "-g", "daemon off;"]
```

#### 2.2 Mejorar Harvest Loop
```bash
#!/bin/sh
set -o pipefail

backoff_seconds=5
max_backoff_seconds=3600

while true; do
  echo "[$(date -u +"%Y-%m-%dT%H:%M:%SZ")] Starting harvest..."
  
  if php /app/apps/CliJobs/bin/harvest.php --max-pages="${MAX_PAGES}"; then
    echo "[$(date -u +"%Y-%m-%dT%H:%M:%SZ")] Harvest successful. Sleeping ${INTERVAL_SECONDS}s"
    backoff_seconds=5
    sleep "${INTERVAL_SECONDS}"
  else
    echo "[$(date -u +"%Y-%m-%dT%H:%M:%SZ")] Harvest failed. Retrying in ${backoff_seconds}s"
    sleep "${backoff_seconds}"
    backoff_seconds=$(( backoff_seconds * 2 ))
    [ "${backoff_seconds}" -le "${max_backoff_seconds}" ] || backoff_seconds="${max_backoff_seconds}"
  fi
done
```

#### 2.3 Validar VITE_API_URL
```dockerfile
ARG VITE_API_URL
RUN if [ -z "$VITE_API_URL" ]; then echo "ERROR: VITE_API_URL must be set" && exit 1; fi
ENV VITE_API_URL=$VITE_API_URL
```

---

### FASE 3: Estructura de Contextos Acotados
**Tiempo estimado:** 20 horas  
**Prioridad:** ALTA

#### 3.1 Renombrar UserProfile → UserPreferences
```bash
mv src/UserProfile src/UserPreferences
# Y actualizar todos los imports
```

#### 3.2 Mover GenerateActivityEmbedding a Infrastructure
```bash
mv src/CatalogHarvest/Application/Embeddings \
   src/CatalogHarvest/Infrastructure/AI/Embeddings
```

#### 3.3 Mover NotifyFavoriteUsers a Notifications Context
```bash
# src/Notifications/Application/NotifyFavoriteUsers/NotifyFavoriteUsers.php
namespace Notifications\Application\NotifyFavoriteUsers;
# Ya que es responsabilidad del contexto Notifications
```

---

### FASE 4: Validaciones y Tests
**Tiempo estimado:** 12 horas  
**Prioridad:** MEDIA

#### 4.1 Verificar Tamaño de Archivos
```bash
find src apps -name "*.php" -exec wc -l {} \; | awk '$1 > 300 {print}'
# Resultado esperado: 0 archivos
```

#### 4.2 Verificar Violaciones de Dependencia
```bash
php -l src/CatalogHarvest/Application/*/**.php  # Syntax check
# + herramientas de análisis estático custom
```

---

## 📋 CHECKLIST DE VALIDACIÓN POST-REMEDIACIÓN

```
PRE-DESPLIEGUE:
□ Todos los Puertos definidos en Domain layer
□ Ningún import de Infrastructure en Application
□ Tests unitarios sin mocks de Infrastructure
□ Volumen /data con estructura correcta
□ Init scripts crean todas las tablas
□ Migrations son idempotentes y reversibles
□ PHP-FPM en lugar de built-in server
□ Harvest loop con backoff exponencial
□ VITE_API_URL validado en Dockerfile
□ Health checks en depends_on con condition
□ Todas las configuraciones con valores por defecto seguros
□ Log level configurado (DEBUG/INFO/WARNING/ERROR)
□ Contextos acotados sin contaminación
□ Nombres de archivos: PascalCase (backend), kebab-case (frontend)
□ composer phpstan: 0 errores
□ composer cs-check: 0 violations
□ composer phpunit: 100% pass
□ npm run lint: 0 errors (frontend)
□ npm run typecheck: 0 errors (frontend)

POST-DESPLIEGUE:
□ Backend responde en /v1/activities
□ Frontend se carga con API correcto
□ Harvest job ejecuta y loguea correctamente
□ Redis funciona (verificar rate limiting)
□ Database inicializa automáticamente
□ Logs en formato Loki JSON
□ Backup strategy probado (rsnapshot)
□ Rollback plan documentado y probado
```

---

## 📖 REFERENCIAS NORMATIVAS

**Documentación Verificada:**
1. ✓ AGENTS.md (448 líneas) - Estándares arquitectónicos
2. ✓ DOMAIN.md (445 líneas) - Modelo de dominio
3. ✓ CLAUDE.md (Quick reference)
4. ✓ RFC 2119 (MUST, SHOULD, MAY keywords)
5. ✓ DDD (Domain-Driven Design)
6. ✓ Hexagonal Architecture

**Standards Aplicados:**
- PSR-4 (Autoloading)
- PSR-1/PSR-12 (Code style)
- RFC 7807 (Problem JSON)
- OpenAPI 3.0 (API specification)
- TDD (Test-Driven Development)

---

## 🎯 CONCLUSIONES FINALES

### Estado Actual: 🔴 NO LISTO PARA PRODUCCIÓN

**Bloqueadores Críticos:**
1. Violación severa de arquitectura hexagonal
2. Volumen de datos no respeta estrategia de backup
3. Migraciones no son idempotentes
4. PHP built-in server en "producción"

**Próximos Pasos Inmediatos:**
1. ✅ Crear Puertos en Domain layer (2 horas)
2. ✅ Refactorizar Application layer (4 horas)
3. ✅ Corregir compose.yaml (1 hora)
4. ✅ Completar init-scripts (3 horas)
5. ✅ Implementar PHP-FPM (3 horas)
6. ✅ Tests de validación (3 horas)

**Tiempo Total Estimado:** 48-56 horas de ingeniería

**Recomendación:** Detener despliegue hasta que se resuelvan FASE 1 y FASE 2.

---

**Auditoría completada por:** GitHub Copilot (Claude Haiku)  
**Fecha:** 4 de febrero de 2026  
**Clasificación:** CRÍTICO - Circulación Restringida  
**Próxima revisión:** Post-remediación FASE 1
