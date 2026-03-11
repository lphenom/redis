.PHONY: up down test lint lint-fix analyse check kphp-check install shell

# Docker compose command
DC = docker compose

## Start development environment
up:
	$(DC) up -d --build

## Stop development environment
down:
	$(DC) down

## Install dependencies inside container
install:
	$(DC) run --rm php composer install

## Run PHPUnit tests
test:
	$(DC) run --rm php vendor/bin/phpunit --colors=always

## Run PHP CS Fixer (dry-run, show diff)
lint:
	$(DC) run --rm php vendor/bin/php-cs-fixer fix --dry-run --diff --allow-risky=yes

## Run PHP CS Fixer (auto-fix)
lint-fix:
	$(DC) run --rm php vendor/bin/php-cs-fixer fix --allow-risky=yes

## Run PHPStan static analysis
analyse:
	$(DC) run --rm php vendor/bin/phpstan analyse --memory-limit=256M

## Run lint + analyse + tests
check: lint analyse test

## Build KPHP binary + PHAR (check compatibility)
kphp-check:
	docker build -f Dockerfile.check -t lphenom-redis-check .

## Open shell inside php container
shell:
	$(DC) run --rm php sh

## Show help
help:
	@echo "Available commands:"
	@echo "  make up           — Start Docker environment"
	@echo "  make down         — Stop Docker environment"
	@echo "  make install      — Install Composer dependencies"
	@echo "  make test         — Run PHPUnit tests"
	@echo "  make lint         — Run PHP CS Fixer (dry-run)"
	@echo "  make lint-fix     — Run PHP CS Fixer (auto-fix)"
	@echo "  make analyse      — Run PHPStan"
	@echo "  make check        — Run lint + analyse + tests"
	@echo "  make kphp-check   — Build KPHP binary + PHAR"
	@echo "  make shell        — Open shell in PHP container"
	@echo "  make tui          — Launch interactive Redis TUI"


