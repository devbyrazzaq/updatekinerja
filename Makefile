# =====================================================
# Makefile — Project Laravel
# =====================================================

PROJECT     := kinerja
PHP         := kinerja-app-php
NODE        := kinerja-app-node
NGINX       := shared-nginx
MYSQL       := shared-mysql
DB_NAME     := kinerja
DB_PASS     := secret
DOMAIN      := kinerja.orb.local
PORT        := 8080
SHARED_DIR  := /Users/macbookpro/Developers

WORKDIR  := /var/www/$(PROJECT)
ARTISAN  := docker exec -it -w $(WORKDIR) $(PHP) php artisan
COMPOSER := docker exec -it -w $(WORKDIR) $(PHP) composer
NPM      := docker exec -it -w $(WORKDIR) $(NODE) npm

.DEFAULT_GOAL := help

.PHONY: help
help: ## Tampilkan daftar command
	@echo ""
	@echo "🚀 $(PROJECT) — http://$(DOMAIN):$(PORT)"
	@echo "================================================"
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | \
		awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-18s\033[0m %s\n", $$1, $$2}'

# === Setup ===
.PHONY: setup
setup: link-nginx db-create up reload ## Setup project pertama kali (otomatis semua)
	@echo ""
	@echo "✅ Setup selesai!"
	@echo "🌐 Buka: http://$(DOMAIN):$(PORT)"

.PHONY: sleep
sleep: down unlink-nginx reload
	@echo "✅ Project $(PROJECT) dimatikan"

.PHONY: wakeup
wakeup: link-nginx up reload
	@echo "✅ Project $(PROJECT) hidup kembali"

.PHONY: link-nginx
link-nginx: ## Copy config Nginx ke shared
	@rm -f /Users/macbookpro/Developers/docker-shared/nginx/conf.d/$(PROJECT).conf
	@cp $(PWD)/nginx/$(PROJECT).conf /Users/macbookpro/Developers/docker-shared/nginx/conf.d/$(PROJECT).conf
	@echo "✅ Nginx config terhubung"

.PHONY: unlink-nginx
unlink-nginx: ## Lepas symlink Nginx
	@rm -f /Users/macbookpro/Developers/docker-shared/nginx/conf.d/$(PROJECT).conf

.PHONY: db-create
db-create: ## Buat database di MySQL shared
	@docker exec $(MYSQL) mysql -uroot -p$(DB_PASS) \
		-e "CREATE DATABASE IF NOT EXISTS $(DB_NAME) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
	@echo "✅ Database '$(DB_NAME)' siap"

.PHONY: reload
reload: ## Reload Nginx shared
	@docker exec $(NGINX) nginx -s reload
	@echo "✅ Nginx di-reload"

# === Container ===
.PHONY: up
up: ## Hidupkan container project
	docker compose up -d

.PHONY: down
down: ## Matikan container project
	docker compose down

.PHONY: build
build: ## Build ulang image
	docker compose build --no-cache

.PHONY: restart
restart: ## Restart container
	docker compose restart

# === Shell ===
.PHONY: shell
shell: ## Masuk ke container PHP
	docker exec -it $(PHP) bash

.PHONY: node-shell
node-shell: ## Masuk ke container Node
	docker exec -it $(NODE) sh

# === Artisan ===
.PHONY: artisan
artisan: ## Jalankan artisan — usage: make artisan cmd="route:list"
	@$(ARTISAN) $(cmd)

.PHONY: tinker
tinker: ## Buka Laravel Tinker
	@docker exec -it -w $(WORKDIR) $(PHP) php artisan tinker

.PHONY: migrate
migrate: ## Jalankan migration
	@$(ARTISAN) migrate

.PHONY: fresh
fresh: ## Drop semua tabel + migrate + seed
	@$(ARTISAN) migrate:fresh --seed

.PHONY: seed
seed: ## Jalankan seeder
	@$(ARTISAN) db:seed

.PHONY: cache-clear
cache-clear: ## Bersihkan semua cache Laravel
	@$(ARTISAN) cache:clear
	@$(ARTISAN) config:clear
	@$(ARTISAN) route:clear
	@$(ARTISAN) view:clear

# === Composer ===
.PHONY: composer-install
composer-install: ## composer install
	@$(COMPOSER) install

.PHONY: composer-require
composer-require: ## composer require — usage: make composer-require pkg=vendor/package
	@$(COMPOSER) require $(pkg)

# === NPM ===
.PHONY: npm-install
npm-install: ## npm install
	@$(NPM) install

.PHONY: npm-build
npm-build: ## Build asset untuk production
	@$(NPM) run build

.PHONY: vite
vite: ## Lihat log Vite
	docker logs -f $(NODE)

# === Database ===
.PHONY: db
db: ## Masuk MySQL CLI database project
	@docker exec -it $(MYSQL) mysql -uroot -p$(DB_PASS) $(DB_NAME)

# === Logs ===
.PHONY: logs
logs: ## Lihat semua log project
	docker compose logs -f

.PHONY: php-logs
php-logs: ## Lihat log PHP
	docker logs -f $(PHP)

# === Cleanup ===
.PHONY: fix-perm
fix-perm: ## Fix permission storage
	@docker exec $(PHP) chmod -R 775 storage bootstrap/cache
	@docker exec $(PHP) chown -R www-data:www-data storage bootstrap/cache
	@echo "✅ Permission diperbaiki"
