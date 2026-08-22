# syntax=docker/dockerfile:1.7

FROM node:24-alpine AS frontend
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund
COPY resources ./resources
COPY tsconfig.json vite.config.ts ./
RUN npm run build

FROM composer:2.10 AS composer

FROM php:8.5-fpm-alpine AS php-base

RUN apk add --no-cache \
        curl \
        freetype \
        icu-libs \
        libjpeg-turbo \
        libpng \
        libpq \
        libzip \
        nginx \
        tesseract-ocr \
        tesseract-ocr-data-eng \
    && apk add --no-cache --virtual .build-deps \
        $PHPIZE_DEPS \
        freetype-dev \
        icu-dev \
        libjpeg-turbo-dev \
        libpng-dev \
        libzip-dev \
        postgresql-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        gd \
        intl \
        pcntl \
        pdo_pgsql \
        zip \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps \
    && php -r 'foreach (["dom", "gd", "intl", "mbstring", "pcntl", "pdo_pgsql", "redis", "SimpleXML", "xml", "xmlwriter", "zip"] as $extension) { if (! extension_loaded($extension)) { fwrite(STDERR, "Missing PHP extension: {$extension}\n"); exit(1); } } if (! function_exists("opcache_get_status")) { fwrite(STDERR, "Missing PHP OPcache API\n"); exit(1); }'

FROM php-base AS vendor
WORKDIR /app
COPY --from=composer /usr/bin/composer /usr/local/bin/composer
RUN apk add --no-cache git
COPY composer.json composer.lock artisan ./
COPY app ./app
COPY bootstrap ./bootstrap
COPY config ./config
COPY database ./database
COPY public ./public
COPY resources/views ./resources/views
COPY routes ./routes
RUN composer install \
    --no-dev \
    --no-interaction \
    --no-progress \
    --no-scripts \
    --prefer-dist \
    --optimize-autoloader

FROM php-base AS runtime

ARG APP_VERSION=dev
ARG RELEASE_SHA=local

ENV APP_VERSION=${APP_VERSION} \
    RELEASE_SHA=${RELEASE_SHA} \
    PHP_OPCACHE_VALIDATE_TIMESTAMPS=0

LABEL org.opencontainers.image.title="Kingshot Alliance" \
      org.opencontainers.image.source="https://github.com/awalker0878/kingshot-alliance" \
      org.opencontainers.image.version="${APP_VERSION}" \
      org.opencontainers.image.revision="${RELEASE_SHA}" \
      org.opencontainers.image.licenses="GPL-3.0-only"

WORKDIR /var/www/html

COPY --from=vendor /app/vendor ./vendor
COPY app ./app
COPY bootstrap ./bootstrap
COPY config ./config
COPY database ./database
COPY public ./public
COPY resources/views ./resources/views
COPY routes ./routes
COPY storage ./storage
COPY artisan composer.json composer.lock LICENSE ./
COPY --from=frontend /app/public/build ./public/build
COPY docker/nginx/nginx.conf /etc/nginx/nginx.conf
COPY docker/nginx/default.conf /etc/nginx/http.d/default.conf
COPY docker/nginx/azure.conf /etc/nginx/azure.conf
COPY docker/php/php.ini /usr/local/etc/php/conf.d/99-kingshot.ini
COPY docker/php/opcache.ini /usr/local/etc/php/conf.d/10-opcache.ini
COPY docker/entrypoint.sh /usr/local/bin/kingshot-entrypoint

RUN nginx -t -c /etc/nginx/azure.conf \
    && tesseract --version >/dev/null \
    && php artisan package:discover --ansi \
    && chmod +x /usr/local/bin/kingshot-entrypoint \
    && chown -R www-data:www-data storage bootstrap/cache

USER www-data

EXPOSE 8080 9000

ENTRYPOINT ["kingshot-entrypoint"]
CMD ["php-fpm"]

FROM runtime AS development

USER root
COPY --from=composer /usr/bin/composer /usr/local/bin/composer
RUN apk add --no-cache bash git

ENV PHP_OPCACHE_VALIDATE_TIMESTAMPS=1
