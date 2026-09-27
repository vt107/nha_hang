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

# ===== Production (máy chủ): xem docs/deploy.md =====
PROD = docker compose -f compose.prod.yaml

.PHONY: prod-build prod-up prod-down prod-logs prod-ps prod-artisan prod-shell prod-ssl prod-backup prod-restore prod-deploy

prod-build:
	$(PROD) build --pull

prod-up:
	$(PROD) up -d --remove-orphans

prod-down:
	$(PROD) down

prod-logs:
	$(PROD) logs -f --tail=100 nginx app horizon reverb scheduler

prod-ps:
	$(PROD) ps

prod-artisan:
	$(PROD) exec app php artisan $(c)

prod-shell:
	$(PROD) exec app sh

# Cấp chứng chỉ SSL lần đầu (cần SERVER_NAME + LETSENCRYPT_EMAIL trong .env, domain đã trỏ về máy chủ)
prod-ssl:
	@set -a; . ./.env; set +a; \
	$(PROD) run --rm --entrypoint certbot certbot certonly --webroot -w /var/www/certbot \
		-d "$$SERVER_NAME" --email "$$LETSENCRYPT_EMAIL" --agree-tos --no-eff-email --non-interactive
	$(PROD) restart nginx

prod-backup:
	$(PROD) run --rm backup once

# make prod-restore file=nha_hang-20260928-030000.sql.gz
prod-restore:
	$(PROD) run --rm backup restore $(file)
	$(PROD) exec app php artisan cache:clear

prod-deploy:
	./deploy.sh
