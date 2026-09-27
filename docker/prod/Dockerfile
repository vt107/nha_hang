# Image production: code + vendor + asset đóng gói sẵn, không mount source.
#   target "app"   : php-fpm (dùng chung cho app / horizon / scheduler / reverb)
#   target "nginx" : nginx + thư mục public (asset tĩnh)

# ---------- Asset Vite ----------
FROM node:22-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json vite.config.js ./
RUN npm ci --no-audit --no-fund
COPY resources ./resources
COPY app ./app
# Key Reverb được nhúng vào JS lúc build (import.meta.env).
ARG VITE_REVERB_APP_KEY
ENV VITE_REVERB_APP_KEY=${VITE_REVERB_APP_KEY}
RUN npm run build

# ---------- PHP base ----------
FROM php:8.4-fpm-bookworm AS base
COPY --from=mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions pdo_mysql bcmath intl zip gd exif pcntl opcache redis
COPY docker/prod/php.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY docker/prod/php-fpm.conf /usr/local/etc/php-fpm.d/zz-app.conf
WORKDIR /var/www/html

# ---------- Composer vendor ----------
FROM base AS vendor
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction --no-progress

# ---------- App ----------
FROM base AS app
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY --from=vendor /var/www/html/vendor ./vendor
COPY . .
COPY --from=assets /app/public/build ./public/build
RUN composer dump-autoload --optimize --classmap-authoritative --no-dev \
    && php artisan package:discover --ansi \
    && php artisan filament:assets \
    && rm -f public/storage && ln -s /var/www/html/storage/app/public public/storage \
    && mkdir -p storage/app/public storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && rm /usr/bin/composer
COPY --chmod=755 docker/prod/entrypoint.sh /usr/local/bin/app-entrypoint
USER www-data
ENTRYPOINT ["app-entrypoint"]
CMD ["php-fpm"]

# ---------- Nginx ----------
FROM nginx:1.27-alpine AS nginx
COPY --from=app /var/www/html/public /var/www/html/public
COPY docker/prod/nginx/nginx.conf /etc/nginx/nginx.conf
COPY docker/prod/nginx/snippets /etc/nginx/snippets
COPY docker/prod/nginx/templates /etc/nginx/app-templates
COPY --chmod=755 docker/prod/nginx/40-select-site.sh /docker-entrypoint.d/40-select-site.sh
COPY --chmod=755 docker/prod/nginx/healthcheck.sh /usr/local/bin/healthcheck
RUN rm -f /etc/nginx/conf.d/default.conf && mkdir -p /var/www/certbot
