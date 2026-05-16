.PHONY: up down app test stan fix seed build-assets

up:
	docker compose up -d --build

down:
	docker compose down

app:
	docker compose exec app bash

test:
	docker compose exec -e APP_ENV=test app php bin/phpunit

stan:
	docker compose exec app ./vendor/bin/phpstan analyse --memory-limit=512M

fix:
	docker compose exec app ./vendor/bin/php-cs-fixer fix

seed:
	docker compose exec app php bin/console app:seed

build-assets:
	docker compose exec app php bin/console tailwind:build
	docker compose exec app php bin/console asset-map:compile
