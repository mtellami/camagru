.PHONY: up down test lint logs reset
up:
	php scripts/setup.php
	docker compose up --build -d --wait
	docker compose exec -T web php -r 'echo "Camagru: ".getenv("APP_URL")." | Inbox: http://localhost:".getenv("MAILPIT_PORT").PHP_EOL;'
down:
	docker compose down
test:
	docker compose exec -T web php tests/php/run.php
lint:
	find src scripts public resources tests -name '*.php' -print0 | xargs -0 -n1 php -l
	node --check public/assets/js/app.js
logs:
	docker compose logs --tail=100
reset:
	@printf 'Delete all local Camagru data? Type DELETE: '; read answer; test "$$answer" = DELETE
	docker compose down --volumes
