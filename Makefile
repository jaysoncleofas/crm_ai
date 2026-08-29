# Jayson CRM — common tasks. Everything runs inside Docker; no local PHP needed.
COMPOSE ?= docker compose
APP     ?= $(COMPOSE) run --rm --no-deps app

.DEFAULT_GOAL := help
.PHONY: help setup up down restart logs shell migrate seed-if-empty fresh seed test test-e2e lint build dev ps

help: ## Show this help
	@grep -hE '^[a-zA-Z_-]+:.*?## ' $(MAKEFILE_LIST) | awk 'BEGIN{FS=":.*?## "}{printf "  \033[36m%-12s\033[0m %s\n", $$1, $$2}'

setup: ## First run: build images, install deps, migrate + seed, build assets
	cp -n .env.example .env || true
	$(COMPOSE) build
	$(COMPOSE) up -d redis
	$(APP) composer install
	$(APP) php artisan key:generate
	$(APP) php artisan migrate --seed
	npm install
	npm run build
	$(COMPOSE) up -d

up: ## Start the stack
	$(COMPOSE) up -d

down: ## Stop the stack
	$(COMPOSE) down

restart: ## Restart app containers
	$(COMPOSE) restart app queue scheduler web

ps: ## Show service status
	$(COMPOSE) ps

logs: ## Tail application logs
	$(COMPOSE) logs -f app web queue

shell: ## Shell into the app container
	$(COMPOSE) exec app sh

migrate: ## Run pending migrations
	$(APP) php artisan migrate
	@$(MAKE) seed-if-empty

seed-if-empty: ## Seed demo data when the users table is empty
	@count=$$($(APP) php artisan tinker --execute="echo App\\Models\\User::count();" 2>/dev/null | tail -1); \
	if [ "$$count" = "0" ]; then \
		echo "No users found — seeding demo data..."; \
		$(APP) php artisan db:seed; \
	else \
		echo "Database has $$count user(s) — skipping seed."; \
	fi

fresh: ## Drop, re-migrate and re-seed the database
	$(APP) php artisan migrate:fresh --seed

seed: ## Re-run the seeders
	$(APP) php artisan db:seed

test: ## Run the Pest suite (separate crm_testing database)
	$(APP) php artisan test

test-e2e: ## Run Playwright browser tests against the running stack
	npx playwright test

lint: ## Format PHP with Pint
	$(APP) ./vendor/bin/pint

build: ## Build frontend assets
	npm run build

dev: ## Run Vite with hot reload
	npm run dev
