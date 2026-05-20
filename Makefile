.PHONY: up down build logs shell prod

up:
	docker compose up --build

down:
	docker compose down

build:
	docker compose build

logs:
	docker compose logs -f

shell:
	docker compose exec app sh

migrate:
	docker compose exec app php artisan migrate

psql:
	docker compose exec postgres psql -U pandora -d pandora

prod:
	docker compose -f docker-compose.prod.yml up --build
