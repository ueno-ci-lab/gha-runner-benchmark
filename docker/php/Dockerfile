# syntax=docker/dockerfile:1
# ローカル開発 / CI 共通の PHP イメージ（バージョンをここで一元管理する）
ARG PHP_VERSION=8.5

FROM php:${PHP_VERSION}-cli-bookworm AS base

COPY --from=mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip \
    && rm -rf /var/lib/apt/lists/* \
    && install-php-extensions pdo_mysql intl zip bcmath pcntl opcache

ENV COMPOSER_ALLOW_SUPERUSER=1

WORKDIR /var/www/html

# ---- ローカル開発用（ソースはバインドマウント） ----
FROM base AS dev

ARG UID=1000
ARG GID=1000
RUN groupadd -g ${GID} app && useradd -m -u ${UID} -g ${GID} app
USER app

# ---- CI 用（ソースをイメージに焼き込む） ----
FROM base AS ci

# 依存関係のレイヤーを先に作り、ソース変更時もキャッシュが効くようにする
COPY composer.json composer.lock ./
RUN composer install --no-interaction --no-progress --prefer-dist --no-scripts --no-autoloader

COPY . .
RUN composer dump-autoload --optimize \
    && cp .env.example .env \
    && php artisan key:generate
