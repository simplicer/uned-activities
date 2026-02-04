# CORRECCIONES DETALLADAS - Ejemplos de Código

**Documento técnico con implementaciones concretas para resolver errores**

---

## 1️⃣ Corrección EA-1: Crear Puertos para Parsers

### PASO 1: Crear interfaz HtmlParser

```php
<?php
declare(strict_types=1);

namespace CatalogHarvest\Domain\Port;

/**
 * Port for parsing HTML activity details.
 * Implementation-agnostic interface for HTML extraction.
 */
interface HtmlParser
{
    /**
     * Parse activity detail page.
     *
     * @param string $html Raw HTML content
     * @return array Normalized activity data with keys:
     *   - title: string|null
     *   - description: string|null
     *   - startDate: DateTimeImmutable|null
     *   - endDate: DateTimeImmutable|null
     *   - modality: string|null
     *   - center: string|null
     *   - typology: string|null
     *   - area: string|null
     *   - priceAmount: int|null (in cents)
     *   - priceCurrency: string|null
     *   - isFree: bool
     *   - enrollmentOpen: bool|null
     *   - enrollmentStartDate: DateTimeImmutable|null
     *   - enrollmentEndDate: DateTimeImmutable|null
     *   - enrollmentLink: string|null
     *   - credits: int|null
     *   - hasLive: bool|null
     *   - hasRecorded: bool|null
     *   - pricingTable: array|null
     *   - staff: array|null
     *   - sessions: array|null
     *   - targetAudience: string|null
     *   - requirements: array|null
     *   - locationDetails: string|null
     *   - scheduleDetails: string|null
     *   - imageUrl: string|null
     *
     * @throws HtmlParseException on parse error
     */
    public function parse(string $html): array;
}
```

**Ubicación:** `src/CatalogHarvest/Domain/Port/HtmlParser.php`

---

### PASO 2: Crear implementación en Infrastructure

```php
<?php
declare(strict_types=1);

namespace CatalogHarvest\Infrastructure\Http;

use CatalogHarvest\Domain\Port\HtmlParser;

/**
 * XPath-based HTML parser for UNED activity details.
 */
final class XPathActivityDetailParser implements HtmlParser
{
    /**
     * Parse activity detail page using XPath.
     */
    public function parse(string $html): array
    {
        // Implementación actual de ActivityDetailParser
        // pero ahora como implementación de HtmlParser
        
        $dom = new \DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);
        $dom->loadHTML($html);
        libxml_use_internal_errors(false);

        $xpath = new \DOMXPath($dom);

        // ... resto de parsing logic ...

        return [
            'title' => $title ?? null,
            'description' => $description ?? null,
            'startDate' => $startDate,
            'endDate' => $endDate,
            // ... otros campos ...
        ];
    }
}
```

**Ubicación:** `src/CatalogHarvest/Infrastructure/Http/XPathActivityDetailParser.php`

---

### PASO 3: Crear interfaz ActivityEmbedder

```php
<?php
declare(strict_types=1);

namespace CatalogHarvest\Domain\Port;

use CatalogHarvest\Domain\Entity\Activity;

/**
 * Port for generating activity embeddings (semantic search).
 */
interface ActivityEmbedder
{
    /**
     * Generate and store embedding for activity.
     *
     * @throws EmbeddingException on generation error
     */
    public function embed(Activity $activity): void;

    /**
     * Find similar activities by embedding.
     *
     * @param Activity $activity Reference activity
     * @param int $limit Maximum number of results
     * @return array<string> Activity IDs
     */
    public function findSimilar(Activity $activity, int $limit = 10): array;
}
```

**Ubicación:** `src/CatalogHarvest/Domain/Port/ActivityEmbedder.php`

---

### PASO 4: Crear interfaz UserNotifier

```php
<?php
declare(strict_types=1);

namespace CatalogHarvest\Domain\Port;

use CatalogHarvest\Domain\Entity\Activity;

/**
 * Port for notifying users about activity changes.
 */
interface UserNotifier
{
    /**
     * Notify users who favorited this activity about changes.
     *
     * @param Activity $activity The changed activity
     * @param string $changeType Type of change (created, updated, price-changed, archived)
     *
     * @throws NotificationException on notification error
     */
    public function notifyFavoritesOfChange(Activity $activity, string $changeType): void;
}
```

**Ubicación:** `src/CatalogHarvest/Domain/Port/UserNotifier.php`

---

### PASO 5: Refactorizar RefreshActivity

```php
<?php
declare(strict_types=1);

namespace CatalogHarvest\Application\RefreshActivity;

use CatalogHarvest\Domain\Entity\Activity;
use CatalogHarvest\Domain\Port\ActivityRepository;
use CatalogHarvest\Domain\Port\ActivitySnapshotRepository;
use CatalogHarvest\Domain\Port\ActivitySnapshot;
use CatalogHarvest\Domain\Port\PriceSnapshotRepository;
use CatalogHarvest\Domain\Port\PriceSnapshot;
use CatalogHarvest\Domain\Port\HtmlFetcher;
use CatalogHarvest\Domain\Port\HtmlParser;           // ← Nuevo
use CatalogHarvest\Domain\Port\ActivityEmbedder;     // ← Nuevo
use CatalogHarvest\Domain\Port\UserNotifier;         // ← Nuevo
use CatalogHarvest\Domain\ValueObject\ActivityId;
use Psr\Log\LoggerInterface;

/**
 * RefreshActivity use case - CORRECTED.
 *
 * Fetches activity detail page, normalizes fields,
 * stores snapshots and price changes.
 *
 * ✓ RESPETA: Infrastructure → Application → Domain
 * ✓ NO DEPENDE: De clases Infrastructure concretas
 * ✓ INYECTA: Todas las dependencias por constructor
 */
final readonly class RefreshActivity
{
    public function __construct(
        private HtmlFetcher $htmlFetcher,
        private ActivityRepository $activityRepository,
        private ActivitySnapshotRepository $snapshotRepository,
        private PriceSnapshotRepository $priceSnapshotRepository,
        private HtmlParser $parser,                      // ← Port (no implementación)
        private ?ActivityEmbedder $embedder = null,      // ← Port opcional
        private ?UserNotifier $notifier = null,          // ← Port opcional
        private ?LoggerInterface $logger = null,         // ← Logger opcional
    ) {
    }

    /**
     * Refresh an activity from its detail page.
     *
     * @throws \RuntimeException if activity not found
     */
    public function refresh(ActivityId $activityId): void
    {
        // Fetch existing activity
        $activity = $this->activityRepository->findById($activityId);

        if (!$activity instanceof Activity) {
            throw new \RuntimeException("Activity not found: {$activityId}");
        }

        // Fetch HTML from detail page
        $html = $this->htmlFetcher->fetch($activity->url);

        // Parse HTML (ahora usando Port)
        try {
            $data = $this->parser->parse($html);
        } catch (\Exception $e) {
            $this->log('error', 'HTML parse failed', [
                'activity_id' => $activityId->toString(),
                'error' => $e->getMessage(),
            ]);
            throw new \RuntimeException('Failed to parse activity details');
        }

        // Calculate new hash
        $newHash = $this->calculateHash($data);

        // Check if changed
        $hasChanged = $activity->hasChanged($newHash);

        // Update activity with refresh data
        $updatedActivity = $activity->withRefreshData(
            title: $data['title'],
            description: $data['description'],
            startDate: $data['startDate'],
            endDate: $data['endDate'],
            modality: $data['modality'],
            center: $data['center'],
            typology: $data['typology'],
            area: $data['area'],
            priceAmount: $data['priceAmount'],
            priceCurrency: $data['priceCurrency'],
            isFree: $data['isFree'] ?? false,
            enrollmentOpen: $data['enrollmentOpen'],
            enrollmentStartDate: $data['enrollmentStartDate'],
            enrollmentEndDate: $data['enrollmentEndDate'],
            enrollmentLink: $data['enrollmentLink'],
            newHash: $newHash,
            credits: $data['credits'] ?? null,
            hasLive: $data['hasLive'] ?? null,
            hasRecorded: $data['hasRecorded'] ?? null,
            pricingTable: $data['pricingTable'] ?? null,
            staff: $data['staff'] ?? null,
            sessions: $data['sessions'] ?? null,
            targetAudience: $data['targetAudience'] ?? null,
            requirements: $data['requirements'] ?? null,
            locationDetails: $data['locationDetails'] ?? null,
            scheduleDetails: $data['scheduleDetails'] ?? null,
            imageUrl: $data['imageUrl'] ?? null,
        );

        $this->activityRepository->save($updatedActivity);

        // Generar embedding usando Port (si disponible)
        if ($this->embedder !== null) {
            try {
                $this->embedder->embed($updatedActivity);
            } catch (\Exception $e) {
                $this->log('warning', 'Embedding generation failed', [
                    'activity_id' => $activityId->toString(),
                    'error' => $e->getMessage(),
                ]);
                // No falla el refresh si embedding falla
            }
        }

        // Store snapshot if changed
        if ($hasChanged) {
            $changeType = $this->determineChangeType($activity, $updatedActivity);

            $this->snapshotRepository->store(new ActivitySnapshot(
                activityId: $activityId,
                capturedAt: new \DateTimeImmutable(),
                data: $this->serializeActivity($updatedActivity),
                hash: $newHash,
                changeType: $changeType,
            ));

            // Notificar usuarios usando Port (si disponible)
            if ($this->notifier !== null) {
                try {
                    $this->notifier->notifyFavoritesOfChange($updatedActivity, $changeType);
                } catch (\Exception $e) {
                    $this->log('warning', 'User notification failed', [
                        'activity_id' => $activityId->toString(),
                        'change_type' => $changeType,
                        'error' => $e->getMessage(),
                    ]);
                    // No falla el refresh si notification falla
                }
            }

            $this->log('info', 'Activity changed', [
                'activity_id' => $activityId->toString(),
                'change_type' => $changeType,
            ]);
        }

        // Store price snapshot if price changed
        if ($this->hasPriceChanged($activity, $updatedActivity)) {
            $this->priceSnapshotRepository->store(new PriceSnapshot(
                activityId: $activityId,
                capturedAt: new \DateTimeImmutable(),
                priceAmount: $updatedActivity->priceAmount,
                priceCurrency: $updatedActivity->priceCurrency,
            ));
        }
    }

    private function calculateHash(array $data): string
    {
        return hash('sha256', json_encode($data, JSON_THROW_ON_ERROR));
    }

    private function determineChangeType(Activity $old, Activity $new): string
    {
        if ($old->title === null && $new->title !== null) {
            return 'created';
        }

        if ($this->hasPriceChanged($old, $new)) {
            return 'price-changed';
        }

        return 'updated';
    }

    private function hasPriceChanged(Activity $old, Activity $new): bool
    {
        return $old->priceAmount !== $new->priceAmount;
    }

    private function serializeActivity(Activity $activity): array
    {
        return [
            'id' => $activity->id->toString(),
            'uned_id' => $activity->unedId,
            'url' => $activity->url,
            'title' => $activity->title,
            'description' => $activity->description,
            'start_date' => $activity->startDate?->format('Y-m-d H:i:s'),
            'end_date' => $activity->endDate?->format('Y-m-d H:i:s'),
            'modality' => $activity->modality,
            'center' => $activity->center,
            'typology' => $activity->typology,
            'area' => $activity->area,
            'price_amount' => $activity->priceAmount,
            'price_currency' => $activity->priceCurrency,
            'enrollment_open' => $activity->enrollmentOpen,
            'enrollment_start_date' => $activity->enrollmentStartDate?->format('Y-m-d H:i:s'),
            'enrollment_end_date' => $activity->enrollmentEndDate?->format('Y-m-d H:i:s'),
            'status' => $activity->status,
        ];
    }

    private function log(string $level, string $message, array $context = []): void
    {
        $this->logger?->{$level}($message, $context);
    }
}
```

**Ubicación:** `src/CatalogHarvest/Application/RefreshActivity/RefreshActivity.php` (REEMPLAZAR)

---

## 2️⃣ Corrección DT-1: Docker Compose con Volúmenes Correctos

```yaml
# infra/compose.yaml - CORREGIDO

services:
  db:
    image: supabase/postgres:17.6.1.079
    restart: unless-stopped
    healthcheck:
      test: ["CMD", "pg_isready", "-U", "postgres"]
      interval: 5s
      timeout: 5s
      retries: 10
    environment:
      POSTGRES_PASSWORD: ${POSTGRES_PASSWORD:-postgres}
      POSTGRES_DB: ${POSTGRES_DB:-uned_activities}
      POSTGRES_USER: postgres
    volumes:
      - uned_data:/data/postgres                    # ✓ CORRECTO: mapeo a /data/postgres
      - ./init-scripts:/docker-entrypoint-initdb.d:ro
    networks:
      - app_network
    depends_on:
      - "redis"  # Asegurar que Redis está disponible

  redis:
    image: redis:7-alpine
    restart: unless-stopped
    healthcheck:
      test: ["CMD", "redis-cli", "ping"]
      interval: 5s
      timeout: 3s
      retries: 5
    volumes:
      - uned_data:/data/redis                       # ✓ CORRECTO: mapeo a /data/redis
    networks:
      - app_network

  backend:
    build:
      context: ..
      dockerfile: infra/Dockerfile.backend
    restart: unless-stopped
    env_file:
      - ./env/local.env
    environment:
      - APP_ENV=production
      - APP_DEBUG=false
      - DB_HOST=db
      - DB_PORT=5432
      - DB_NAME=uned_activities
      - DB_USER=postgres
      - DB_PASSWORD=postgres
      - REDIS_HOST=redis
      - REDIS_PORT=6379
      - RATE_LIMIT=100
      - RATE_WINDOW=60
    volumes:
      - uned_data:/data/logs                        # ✓ CORRECTO: logs también en volumen único
    expose:
      - "8080"
    networks:
      - app_network
      - dokploy-network
    depends_on:
      db:
        condition: service_healthy                  # ✓ NUEVO: espera healthcheck
      redis:
        condition: service_healthy                  # ✓ NUEVO: espera healthcheck

  harvester:
    build:
      context: ..
      dockerfile: infra/Dockerfile.backend
    restart: unless-stopped
    env_file:
      - ./env/local.env
    environment:
      - APP_ENV=production
      - APP_DEBUG=false
      - DB_HOST=db
      - DB_PORT=5432
      - DB_NAME=uned_activities
      - DB_USER=postgres
      - DB_PASSWORD=postgres
      - REDIS_HOST=redis
      - REDIS_PORT=6379
      - RATE_LIMIT=100
      - RATE_WINDOW=60
    volumes:
      - uned_data:/data/logs                        # ✓ NUEVO: logs centralizados
    command: ["/bin/sh", "/app/infra/scripts/harvest-loop.sh"]
    depends_on:
      db:
        condition: service_healthy                  # ✓ NUEVO
      redis:
        condition: service_healthy                  # ✓ NUEVO
    networks:
      - app_network

  frontend:
    build:
      context: ..
      dockerfile: infra/Dockerfile.frontend
      args:
        VITE_API_URL: ${VITE_API_URL:-https://api.lexemas.com}
    restart: unless-stopped
    expose:
      - "80"
    networks:
      - dokploy-network

volumes:
  uned_data:                                        # ✓ NUEVO: Named volume único
    driver: local

networks:
  app_network:
    driver: bridge
    internal: true
  dokploy-network:
    external: true
```

---

## 3️⃣ Corrección ED-2: Dependency Injection Centralizado

**Crear archivo DI centralizado:**

```php
<?php
declare(strict_types=1);

namespace Shared\Infrastructure\DependencyInjection;

use DI\Container;
use CatalogHarvest\Domain\Port\ActivityRepository;
use CatalogHarvest\Domain\Port\HtmlParser;
use CatalogHarvest\Domain\Port\ActivityEmbedder;
use CatalogHarvest\Domain\Port\UserNotifier;
use CatalogHarvest\Infrastructure\Http\XPathActivityDetailParser;
use CatalogHarvest\Infrastructure\AI\EmbedderService;
use CatalogHarvest\Infrastructure\Notifications\FavoriteUserNotifier;
// ... otros imports ...

/**
 * Container setup for dependency injection.
 *
 * Centraliza toda la configuración de DI en un lugar.
 * Respeta: Infrastructure → Application → Domain
 */
final class ContainerFactory
{
    public static function create(string $env = 'production'): Container
    {
        $container = new Container();

        // ============================================
        // INFRASTRUCTURE IMPLEMENTATIONS (Layer 1)
        // ============================================

        // HTML Parsers
        $container->set(
            HtmlParser::class,
            \DI\create(XPathActivityDetailParser::class)
        );

        // Embeddings
        $container->set(
            ActivityEmbedder::class,
            \DI\create(EmbedderService::class)
                ->constructor(
                    $_ENV['OPENROUTER_API_KEY'] ?? '',
                    $_ENV['OPENROUTER_EMBEDDING_MODEL'] ?? 'nomic-ai/nomic-embed-text-v1.5'
                )
        );

        // User Notifications
        $container->set(
            UserNotifier::class,
            \DI\create(FavoriteUserNotifier::class)
        );

        // Database Repositories
        $container->set(
            ActivityRepository::class,
            \DI\autowire(PdoActivityRepository::class)
        );

        // ... resto de repositories ...

        // ============================================
        // APPLICATION LAYER (Layer 2)
        // No hay configuración específica aquí
        // Los use-cases se inyectan con autowire
        // ============================================

        // ============================================
        // DOMAIN LAYER (Layer 3)
        // Los value objects y entities no necesitan DI
        // ============================================

        return $container;
    }
}
```

**Ubicación:** `src/Shared/Infrastructure/DependencyInjection/ContainerFactory.php`

**Uso en index.php:**

```php
<?php
// apps/HttpApi/public/index.php

require_once __DIR__ . '/../../../vendor/autoload.php';

use Shared\Infrastructure\DependencyInjection\ContainerFactory;

// ... load env ...

// Crear contenedor
$container = ContainerFactory::create($_ENV['APP_ENV'] ?? 'development');

// Crear aplicación Slim
$app = Slim\Factory\AppFactory::createFromContainer($container);

// ... resto del código ...
```

---

## 4️⃣ Corrección ED-4: Harvest Loop Mejorado

```bash
#!/bin/sh
# infra/scripts/harvest-loop.sh - CORREGIDO

set -o pipefail

# Configuración
MAX_PAGES="${HARVEST_MAX_PAGES:-50}"
INTERVAL_SECONDS="${HARVEST_INTERVAL_SECONDS:-604800}"
MIN_BACKOFF=5
MAX_BACKOFF=3600
CURRENT_BACKOFF=$MIN_BACKOFF

# Logger personalizado
log_info() {
    echo "[$(date -u +"%Y-%m-%dT%H:%M:%SZ")] [INFO] $*" >&2
}

log_error() {
    echo "[$(date -u +"%Y-%m-%dT%H:%M:%SZ")] [ERROR] $*" >&2
}

log_warn() {
    echo "[$(date -u +"%Y-%m-%dT%H:%M:%SZ")] [WARN] $*" >&2
}

# Trap para graceful shutdown
trap 'log_info "Received shutdown signal, exiting..."; exit 0' SIGTERM SIGINT

log_info "Starting harvest loop (MAX_PAGES=$MAX_PAGES, INTERVAL=$INTERVAL_SECONDS seconds)"

while true; do
    log_info "Starting harvest..."
    
    if php /app/apps/CliJobs/bin/harvest.php --max-pages="${MAX_PAGES}" 2>&1 | tee -a /data/logs/harvest.log; then
        log_info "Harvest completed successfully. Next run in ${INTERVAL_SECONDS} seconds"
        CURRENT_BACKOFF=$MIN_BACKOFF  # Reset backoff
        sleep "${INTERVAL_SECONDS}"
    else
        EXIT_CODE=$?
        log_error "Harvest failed with exit code $EXIT_CODE"
        log_warn "Retrying in ${CURRENT_BACKOFF} seconds (backoff)..."
        
        sleep "${CURRENT_BACKOFF}"
        
        # Calcular próximo backoff exponencial
        CURRENT_BACKOFF=$((CURRENT_BACKOFF * 2))
        if [ "$CURRENT_BACKOFF" -gt "$MAX_BACKOFF" ]; then
            CURRENT_BACKOFF=$MAX_BACKOFF
            log_warn "Backoff reached maximum ($MAX_BACKOFF seconds)"
        fi
    fi
done
```

---

## 5️⃣ Corrección DT-5: Dockerfile Backend con PHP-FPM

```dockerfile
# infra/Dockerfile.backend - COMPLETAMENTE REESCRITO

FROM php:8.4-fpm-alpine

# Instalar dependencias del sistema
RUN apk add --no-cache \
    curl \
    git \
    unzip \
    postgresql-client \
    postgresql-dev

# Instalar extensiones PHP
RUN docker-php-ext-install \
    pdo \
    pdo_pgsql \
    opcache

# Instalar Redis extension
RUN pecl install redis && docker-php-ext-enable redis

# Configuración de PHP-FPM
COPY infra/php/php.ini /usr/local/etc/php/php.ini
COPY infra/php/www.conf /usr/local/etc/php-fpm.d/www.conf

# Instalar Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Copiar archivos de proyecto
COPY composer.json composer.lock ./
COPY src/ src/
COPY apps/ apps/
COPY infra/ infra/

# Instalar dependencias (sin dev para producción)
RUN if [ "${APP_ENV}" = "production" ]; then \
    composer install --no-dev --optimize-autoloader; \
    else \
    composer install; \
    fi

# Crear directorio de logs
RUN mkdir -p /data/logs && chmod -R 755 /data/logs

# Cambiar permiso a www-data
RUN chown -R www-data:www-data /app /data/logs

# Usuario no-root
USER www-data

# Healthcheck
HEALTHCHECK --interval=30s --timeout=3s --start-period=10s --retries=3 \
    CMD curl -f http://localhost:9000/ping || exit 1

# PHP-FPM en foreground
CMD ["php-fpm", "-F"]
```

**Ubicación:** `infra/Dockerfile.backend`

**Nuevo archivo:** `infra/php/php.ini`

```ini
# PHP configuration
display_errors = Off
display_startup_errors = Off
log_errors = On
error_log = /data/logs/php-error.log
error_reporting = E_ALL

; Opciones de performance
opcache.enable = 1
opcache.enable_cli = 1
opcache.memory_consumption = 128
opcache.max_accelerated_files = 10000
opcache.validate_timestamps = 1
opcache.revalidate_freq = 2

; Límites de tiempo
max_execution_time = 300
default_socket_timeout = 60

; Memory
memory_limit = 512M

; Uploads
post_max_size = 100M
upload_max_filesize = 100M
```

**Nuevo archivo:** `infra/php/www.conf`

```ini
[www]
listen = 9000
listen.backlog = 4096

; Procesos
pm = dynamic
pm.max_children = 20
pm.start_servers = 5
pm.min_spare_servers = 2
pm.max_spare_servers = 10
pm.max_requests = 500

; Logs
access.log = /data/logs/php-access.log
slowlog = /data/logs/php-slow.log
request_slowlog_timeout = 5s

; Status
pm.status_path = /status
```

---

## 6️⃣ Corrección DT-6: Dockerfile Frontend con Labels

```dockerfile
# infra/Dockerfile.frontend - MEJORADO

FROM node:20-alpine AS build

WORKDIR /app

# Copiar manifests
COPY web/package.json web/package-lock.json ./web/
WORKDIR /app/web

# Instalar dependencias
RUN npm ci

# Copiar código fuente
COPY web/ /app/web

# Validar VITE_API_URL
ARG VITE_API_URL
RUN if [ -z "$VITE_API_URL" ]; then \
    echo "ERROR: VITE_API_URL must be set during build"; \
    exit 1; \
    fi

ENV VITE_API_URL=$VITE_API_URL

# Build
RUN npm run build

# ============================================
# Production stage
# ============================================
FROM nginx:1.25-alpine

# Metadata
LABEL maintainer="UNED Activities Finder Team"
LABEL description="UNED Extension Activities Finder - Frontend"
LABEL version="1.0.0"
LABEL org.opencontainers.image.source="https://github.com/your-org/uned-activities-finder"

# Copy Nginx config
COPY infra/nginx-static.conf /etc/nginx/conf.d/default.conf

# Copy built app from build stage
COPY --from=build /app/web/dist /usr/share/nginx/html

# Healthcheck
HEALTHCHECK --interval=30s --timeout=3s --start-period=10s --retries=3 \
    CMD wget --quiet --tries=1 --spider http://localhost:80/index.html || exit 1

# Non-root user
USER nginx

EXPOSE 80

CMD ["nginx", "-g", "daemon off;"]
```

---

Este documento proporciona implementaciones concretas para cada corrección. Los cambios son:

1. **Arquitectónicamente correctos** (respetan DDD)
2. **Testables** (puertos definidos)
3. **Mantenibles** (clara separación de responsabilidades)
4. **Productivos** (sin vulnerabilidades obviasproceso de remediación iterativo
