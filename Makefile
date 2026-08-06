.PHONY: setup up down shell check test backup restore

setup:
	@test -f .env || cp .env.example .env
	@test -f composer.lock
	@test -f package-lock.json
	docker compose build
	docker compose run --rm app composer install --no-interaction --no-progress --prefer-dist
	docker compose run --rm app php artisan key:generate
	docker compose run --rm app php artisan migrate
	docker compose run --rm node npm ci --no-audit --no-fund
	docker compose up -d

up:
	docker compose up -d

down:
	docker compose down

shell:
	docker compose exec app sh

check:
	docker compose run --rm app composer check
	docker compose run --rm node npm run check

test:
	docker compose run --rm app php artisan test --parallel

backup:
	./bin/backup

restore:
	./bin/restore $(FILE)
