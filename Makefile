.PHONY: help install test quality fmt server infra-up infra-down

# Default target
.DEFAULT_GOAL := help

# Colors for output
BLUE := \033[0;34m
GREEN := \033[0;32m
YELLOW := \033[0;33m
NC := \033[0m # No Color

##@ Help

help: ## Display this help message
	@echo "$(BLUE)UNED Activities Finder$(NC)"
	@echo ""
	@echo "$(GREEN)Usage:$(NC)"
	@echo "  make [target]"
	@echo ""
	@echo "$(GREEN)Targets:$(NC)"
	awk 'BEGIN {FS = ":.*##"} /^[a-zA-Z_-]+:.*?##/ { printf "  $(YELLOW)%-20s$(NC) %s\n", $$1, $$2 }' $(MAKEFILE_LIST)

##@ Installation

install: composer-install web-install ## Install all dependencies

composer-install: ## Install PHP dependencies
	@echo "$(BLUE)Installing PHP dependencies...$(NC)"
	composer install

web-install: ## Install Node dependencies
	@echo "$(BLUE)Installing Node dependencies...$(NC)"
	cd web && npm install

##@ Backend

test: test-unit test-integration test-acceptance ## Run all tests

test-unit: ## Run unit tests
	@echo "$(BLUE)Running unit tests...$(NC)"
	phpunit --testsuite=Unit

test-integration: ## Run integration tests
	@echo "$(BLUE)Running integration tests...$(NC)"
	phpunit --testsuite=Integration

test-acceptance: ## Run acceptance tests
	@echo "$(BLUE)Running acceptance tests...$(NC)"
	phpunit --testsuite=Acceptance

lint: ## Run PHP CS Fixer (dry-run)
	@echo "$(BLUE)Checking code style...$(NC)"
	composer cs-check

fmt: ## Fix code style
	@echo "$(BLUE)Fixing code style...$(NC)"
	composer cs-fix

analyze: ## Run PHPStan static analysis
	@echo "$(BLUE)Running static analysis...$(NC)"
	composer phpstan

quality: lint analyze ## Run all quality checks

audit: ## Run Composer security audit
	@echo "$(BLUE)Running security audit...$(NC)"
	composer audit

server: ## Start PHP development server
	@echo "$(BLUE)Starting PHP development server on :8080$(NC)"
	php -S localhost:8080 -t apps/HttpApi/public

##@ Frontend

web-dev: ## Start frontend dev server
	@echo "$(BLUE)Starting frontend dev server...$(NC)"
	cd web && npm run dev

web-build: ## Build frontend for production
	@echo "$(BLUE)Building frontend...$(NC)"
	cd web && npm run build

web-test: ## Run frontend tests
	@echo "$(BLUE)Running frontend tests...$(NC)"
	cd web && npm run test

web-lint: ## Run ESLint
	@echo "$(BLUE)Linting frontend code...$(NC)"
	cd web && npm run lint

web-typecheck: ## Run TypeScript check
	@echo "$(BLUE)Checking TypeScript types...$(NC)"
	cd web && npx tsc --noEmit

##@ Infrastructure

infra-up: ## Start Docker services (Supabase)
	@echo "$(BLUE)Starting Docker services...$(NC)"
	cd infra && docker compose up -d

infra-down: ## Stop Docker services
	@echo "$(BLUE)Stopping Docker services...$(NC)"
	cd infra && docker compose down

infra-logs: ## Show Docker logs
	cd infra && docker compose logs -f

infra-restart: infra-down infra-up ## Restart Docker services

##@ Database

migrate: ## Run database migrations
	@echo "$(BLUE)Running database migrations...$(NC)"
	php apps/CliJobs/bin/migrate.php migrations:migrate --no-interaction

migrate-status: ## Show migration status
	@echo "$(BLUE)Checking migration status...$(NC)"
	php apps/CliJobs/bin/migrate.php migrations:status

migrate-rollback: ## Rollback last migration
	@echo "$(BLUE)Rolling back last migration...$(NC)"
	php apps/CliJobs/bin/migrate.php migrations:migrate --down --no-interaction

db-reset: ## Reset database (local only)
	@echo "$(YELLOW)WARNING: This will delete all data!$(NC)"
	@read -p "Are you sure? [y/N] " -n 1 -r; \
	echo; \
	if [[ $$REPLY =~ ^[Yy]$$ ]]; then \
		cd infra && docker compose down -v; \
		docker compose up -d; \
		sleep 3; \
		php apps/CliJobs/bin/migrate.php migrations:migrate --no-interaction; \
	fi

##@ Jobs

harvest: ## Run harvest job
	@echo "$(BLUE)Running harvest job...$(NC)"
	php apps/CliJobs/bin/harvest.php

digest: ## Run notification digest
	@echo "$(BLUE)Running notification digest...$(NC)"
	php apps/CliJobs/bin/digest.php

##@ Docker

build: build-backend build-frontend ## Build all containers

build-backend: ## Build backend container
	@echo "$(BLUE)Building backend container...$(NC)"
	docker build -f container/backend.Containerfile -t uned-backend:latest .

build-frontend: ## Build frontend container
	@echo "$(BLUE)Building frontend container...$(NC)"
	docker build -f container/frontend.Containerfile -t uned-frontend:latest .

##@ CI

ci: quality test ## Run CI checks locally
	@echo "$(GREEN)All CI checks passed!$(NC)"
