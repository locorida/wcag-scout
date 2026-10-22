# syntax=docker/dockerfile:1.7
#
# Image for the GitHub Action (action.yml). It mirrors the "runtime" stage of the main
# Dockerfile with one difference: GitHub runs Docker actions as root and mounts the workspace
# owned by another user, so this image does not switch to an unprivileged user
# (https://docs.github.com/actions/creating-actions/dockerfile-support-for-github-actions#user).
ARG PHP_IMAGE=php:8.5-cli-trixie
ARG COMPOSER_IMAGE=composer:2

FROM ${COMPOSER_IMAGE} AS composer

FROM ${PHP_IMAGE}
ENV DEBIAN_FRONTEND=noninteractive \
    CHROME_PATH=/usr/bin/chromium

RUN apt-get update \
 && apt-get install -y --no-install-recommends chromium fonts-liberation fonts-dejavu-core ca-certificates unzip \
 && rm -rf /var/lib/apt/lists/*
ADD --chmod=0755 https://github.com/mlocati/docker-php-extension-installer/releases/download/2.12.3/install-php-extensions /usr/local/bin/
RUN install-php-extensions sockets

COPY --from=composer /usr/bin/composer /usr/local/bin/composer
WORKDIR /opt/wcag-scout
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --no-interaction --no-progress
COPY bin/ bin/
COPY src/ src/
COPY resources/ resources/
RUN composer dump-autoload --no-dev --classmap-authoritative \
 && rm /usr/local/bin/composer

ENTRYPOINT ["php", "/opt/wcag-scout/bin/github-action"]
