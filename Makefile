.PHONY: up down test lint format format-check logs reset
up:
	php tools/scripts/setup.php
	docker compose up --build -d --wait --remove-orphans
	docker compose exec -T web php -r 'echo "Camagru: ".getenv("APP_URL")." | Inbox: http://localhost:".getenv("MAILPIT_PORT").PHP_EOL;'
down:
	docker compose down
test:
	docker compose exec -T web php tests/php/run.php
lint:
	find src tools public tests -name '*.php' -print0 | xargs -0 -n1 php -l
	node --check public/assets/js/app.js
format:
	composer format
format-check:
	composer format:check
logs:
	docker compose logs --tail=100
reset:
	@printf 'Delete all local Camagru data? Type DELETE: '; read answer; test "$$answer" = DELETE
	docker compose down --volumes
