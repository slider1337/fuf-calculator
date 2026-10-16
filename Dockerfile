FROM composer:2.10.3@sha256:af98f42dfff7c68ba8d53c2164fd9fde1087b7d449514baa38c418b1f6bc4bac AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
COPY scripts/patch-openid-nullability.php ./scripts/patch-openid-nullability.php
COPY scripts/copy-bootstrap.php ./scripts/copy-bootstrap.php
COPY scripts/copy-swagger-ui.php ./scripts/copy-swagger-ui.php
RUN composer install --no-interaction --prefer-dist --no-progress

FROM php:8.4.26-cli-bookworm@sha256:836ac6c672d1372a47c8fd61b625015bd07760f493fb2eaab2087636498a2b4b
WORKDIR /var/www/html

RUN apt-get update \
	&& apt-get install -y --no-install-recommends libsqlite3-dev libcurl4-openssl-dev libonig-dev pkg-config \
	&& docker-php-ext-install pdo_sqlite curl mbstring \
	&& pecl install xdebug-3.5.3 \
	&& docker-php-ext-enable xdebug \
	&& rm -rf /var/lib/apt/lists/*

COPY --from=vendor /app/vendor ./vendor
COPY --from=vendor /app/public/assets/vendor/bootstrap ./public/assets/vendor/bootstrap
COPY --from=vendor /app/public/docs/swagger-ui ./public/docs/swagger-ui
COPY . .

EXPOSE 8080
CMD ["php", "-S", "0.0.0.0:8080", "-t", "public", "public/router.php"]
