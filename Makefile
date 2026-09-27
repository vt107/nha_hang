# Lệnh tắt cho môi trường Docker. Ví dụ: make artisan c="route:list"
.PHONY: up down build restart shell artisan composer test fresh logs assets dev

up:
	docker compose up -d

down:
	docker compose down

build:
	docker compose build

# Sau khi sửa code chạy trong worker dài hạn (job, event, broadcast) cần restart horizon / reverb.
restart:
	docker compose restart app horizon reverb scheduler

shell:
	docker compose exec app bash

artisan:
	docker compose exec app php artisan $(c)

composer:
	docker compose exec app composer $(c)

test:
	docker compose exec app php artisan test $(c)

fresh:
	docker compose exec app php artisan migrate:fresh --seed

logs:
	docker compose logs -f app horizon reverb scheduler nginx

assets:
	npm run build

dev:
	npm run dev
