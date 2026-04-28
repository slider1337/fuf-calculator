FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json ./
COPY scripts/copy-bootstrap.php ./scripts/copy-bootstrap.php
COPY scripts/copy-swagger-ui.php ./scripts/copy-swagger-ui.php
RUN composer install --no-interaction --prefer-dist --no-progress

FROM php:8.4-cli
WORKDIR /var/www/html

RUN apt-get update \
	&& apt-get install -y --no-install-recommends libsqlite3-dev pkg-config \
	&& docker-php-ext-install pdo_sqlite \
	&& pecl install xdebug \
	&& docker-php-ext-enable xdebug \
	&& rm -rf /var/lib/apt/lists/*

COPY --from=vendor /app/vendor ./vendor
COPY --from=vendor /app/public/assets/vendor/bootstrap ./public/assets/vendor/bootstrap
COPY --from=vendor /app/public/docs/swagger-ui ./public/docs/swagger-ui
COPY . .

EXPOSE 8080
CMD ["php", "-S", "0.0.0.0:8080", "-t", "public", "public/router.php"]
