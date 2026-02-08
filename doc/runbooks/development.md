# Development Runbook

Guía para desarrolladores que trabajan en UNED Activities Finder.

## Configuración Local

### 1. Instalar Dependencias

```bash
make install
# Esto ejecuta:
# - composer-install
# - web-install
```

### 2. Configurar Variables de Entorno

```bash
cp .env.example .env
# Editar .env con valores locales
```

### 3. Iniciar Servicios

```bash
# Base de datos y Redis
make infra-up

# Servidor PHP (API)
make server &
# En http://localhost:8080

# Frontend (dev server)
make web-dev &
# En http://localhost:5173
```

## Comandos de Desarrollo

### Ejecutar Tests

```bash
# Unit tests
make test

# Integration tests
make test-integration

# E2E tests
npx playwright test

# Con coverage
make test-coverage
```

### Code Quality

```bash
# Lint PHP
make lint

# Fix PHP
make fmt

# Análisis estático
make analyze

# Lint frontend
make web-lint

# Typecheck frontend
make web-typecheck
```

### Jobs de CLI

```bash
# Descubrir actividades
make discover

# Actualizar detalles
make refresh

# Ejecutar digest de notificaciones
make digest
```

## Estructura del Código

```
src/
├── CatalogHarvest/     # Descubrimiento y harvest de actividades
├── CatalogQuery/        # Consulta de catálogo (API pública)
├── Notifications/        # Sistema de notificaciones
├── UserProfile/          # Perfiles y búsquedas guardadas
└── Shared/              # Código compartido
```

## Archivos PHP por Archivo Max Lines < 300

Si necesitas añadir código que exceda las 300 líneas:

1. Extraer clases helper
2. Crear Value Objects
3. Dividir en múltiples servicios
4. Usar Traits solo cuando sea apropiado

## Trabajo con Git

### Rama de Feature

```bash
git checkout -b feature/my-feature
# ... hacer cambios ...
git add -A
git commit -m "feat(scope): description"
git push origin feature/my-feature
```

### Commit Messages

Usar formato Conventional Commits:

```
feat: nueva funcionalidad
fix: corrección de bug
refactor: cambio de estructura
docs: documentación
test: tests
chore: tareas varias
```

### Code Review

1. Crear Pull Request en GitHub
2. Asegurar que todos los tests pasen
3. Esperar aprobación
4. Hacer squash merge a `main`

## Debugging

### API Requests

```bash
# Ver API responses
curl http://localhost:8080/v1/activities | jq

# Con verbosidad
curl -v http://localhost:8080/status
```

### Database Queries

```bash
# Conectar a PostgreSQL
docker compose exec postgres psql -U postgres uned_activities

# Queries útiles
SELECT * FROM activities ORDER BY created_at DESC LIMIT 10;
SELECT * FROM notifications WHERE is_read = false;
```

### Ver Logs

```bash
# Todos los servicios
make infra-logs

# Solo API
docker compose logs -f php

# Con tail y grep
docker compose logs -f php | grep -i error
```

## Patrones de Diseño

### Value Objects

```php
// Value objects son readonly e inmutables
final readonly class UserId
{
    private function __construct(public string $value) {}

    public static function fromString(string $value): self
    {
        return new self($value);
    }
}
```

### Entidades

```php
// Entidades tienen métodos with*() para cambios de estado
final class User
{
    public function withFullName(string $name): self
    {
        return new self(
            // ... preservar inmutabilidad
        );
    }
}
```

### Repositorios

```php
// Repositorios PDO nativos (sin Doctrine DBAL)
final class PdoUserRepository implements UserRepository
{
    // Usar prepared statements para prevenir SQL injection
}
```

## Testing

### Unit Tests

Testea una clase en aislamiento con mocks.

```php
public function testItCalculatesPriceInCents(): void
{
    $activity = Activity::create(..., priceAmount: 15000);
    $this->assertSame(15000, $activity->priceAmount);
}
```

### Integration Tests

Testea integración entre componentes con base de datos real.

```php
public function testItPersistsActivity(): void
{
    $repository = new PdoActivityRepository($pdo);
    // ... ejecutar y verificar
}
```

### E2E Tests

Testea flujos completos de usuario con Playwright.

```typescript
test('user can search activities', async ({ page }) => {
  await page.goto('/activities');
  await page.fill('input[name="search"]', 'fotografía');
  await page.click('button:has-text("Buscar")');
  await expect(page.locator('.activity-card').first()).toBeVisible();
});
```

## Recursos

- `doc/architecture/AGENTS.md` - Estándares arquitectónicos
- `Makefile` - Comandos disponibles
- `README.md` - Información general del proyecto
