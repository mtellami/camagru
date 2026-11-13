.PHONY: up down logs test

up:
	php tools/scripts/setup.php
	docker compose up --build -d --wait --remove-orphans

down:
	docker compose down

logs:
	docker compose logs --tail=100 -f

test:
	docker compose exec -T web php tools/scripts/test-overlays.php
