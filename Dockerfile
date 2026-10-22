# syntax=docker/dockerfile:1.7
#
# Targets:
#   runtime (default)  the tool: docker build -t wcag-scout .
#   dev                + Composer and PCOV for development and CI: docker build --target dev -t wcag-scout:dev .
ARG PHP_IMAGE=php:8.5-cli-trixie
ARG COMPOSER_IMAGE=composer:2

FROM ${COMPOSER_IMAGE} AS composer

# --- base: PHP + Chromium ---------------------------------------------------------------------
FROM ${PHP_IMAGE} AS base

ENV DEBIAN_FRONTEND=noninteractive \
    CHROME_PATH=/usr/bin/chromium

# Chromium from Debian. Fonts: Liberation is metric-compatible with Arial/Times/Courier, so
# pages lay out as intended; DejaVu covers the remaining glyphs.
RUN apt-get update \
 && apt-get install -y --no-install-recommends \
      chromium fonts-liberation fonts-dejavu-core ca-certificates \
 && rm -rf /var/lib/apt/lists/*

# sockets: required by chrome-php's WebSocket client.
ADD --chmod=0755 https://github.com/mlocati/docker-php-extension-installer/releases/download/2.12.3/install-php-extensions /usr/local/bin/
RUN install-php-extensions sockets

# Unprivileged user. Chromium runs without its own sandbox inside the container (it needs
# extra kernel capabilities for that); the container is the isolation boundary. See README.
RUN useradd --create-home --uid 1000 --shell /usr/sbin/nologin scout

# --- dev: Composer + coverage driver -----------------------------------------------------------
FROM base AS dev
RUN install-php-extensions pcov \
 && apt-get update && apt-get install -y --no-install-recommends git unzip && rm -rf /var/lib/apt/lists/*
COPY --from=composer /usr/bin/composer /usr/local/bin/composer
ENV COMPOSER_HOME=/tmp/composer
WORKDIR /app

# --- build: production dependencies -------------------------------------------------------------
FROM dev AS build
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --no-interaction --no-progress
COPY bin/ bin/
COPY src/ src/
COPY resources/ resources/
RUN composer dump-autoload --no-dev --classmap-authoritative

# --- runtime ------------------------------------------------------------------------------------
FROM base AS runtime
COPY --from=build /app /opt/wcag-scout
RUN ln -s /opt/wcag-scout/bin/wcag-scout /usr/local/bin/wcag-scout \
 && mkdir /report && chown scout:scout /report
USER scout
WORKDIR /report
ENTRYPOINT ["wcag-scout"]
CMD ["list"]
