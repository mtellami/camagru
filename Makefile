.PHONY: up down logs

up:
	docker compose up --build -d --wait --remove-orphans

down:
	docker compose down

logs:
	docker compose logs --tail=100 -f
