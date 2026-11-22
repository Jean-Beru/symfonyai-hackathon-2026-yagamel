ARG FRANKENPHP_VERSION="1.13-php8.5-trixie"

FROM dunglas/frankenphp:${FRANKENPHP_VERSION} AS base

# unzip: composer archives.
RUN apt-get update \
    && apt-get install -y --no-install-recommends unzip \
    && rm -rf /var/lib/apt/lists/*

# gd: letterbox of the frames and detection of the table (App\Frame, App\Table).
RUN install-php-extensions gd

COPY --from=composer/composer:2-bin /composer /usr/bin/composer

# One frame of POST /frames: up to 8 MB, a 4K frame. post_max_size: the frame and the fields, the same as the body
# limit of the Caddyfile.
RUN printf 'expose_php = Off\nupload_max_filesize = 8M\npost_max_size = 9M\nmax_execution_time = 20\nmemory_limit = 256M\n' > "$PHP_INI_DIR/conf.d/zz-app.ini"

RUN useradd --uid 1000 --create-home app \
    && mkdir -p /app/var \
    && chown -R app: /app /config/caddy /data/caddy

COPY ./frankenphp/Caddyfile /etc/frankenphp/Caddyfile

ENV COMPOSER_CACHE_DIR="/tmp/composer-cache"

WORKDIR /app
USER app
CMD ["frankenphp", "run", "--config", "/etc/frankenphp/Caddyfile"]

HEALTHCHECK --interval=5s --timeout=5s --start-period=5s --retries=3 CMD curl -fs "http://localhost:${SERVER_PORT:-3000}/health" || exit 1

# Tests: dev dependencies.
FROM base AS dev

ENV APP_ENV="dev"

COPY --chown=app ./app/composer.json ./app/composer.lock ./app/symfony.lock ./
RUN --mount=type=cache,id=composer,target=/tmp/composer-cache,uid=1000 \
    composer install --no-scripts --no-autoloader --prefer-dist --no-progress

COPY --chown=app ./app /app
RUN composer dump-autoload \
    && php bin/console importmap:install

# Production: no dev dependencies, warm cache.
# The code belongs to root: the app user can write only var/.
FROM base AS prod

ENV APP_ENV="prod"

USER root
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
    && chown root: /app

COPY ./app/composer.json ./app/composer.lock ./app/symfony.lock ./
RUN --mount=type=cache,id=composer-prod,target=/tmp/composer-cache \
    composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-progress

COPY ./app /app
RUN composer dump-autoload --no-dev --classmap-authoritative \
    && composer dump-env prod \
    && php bin/console importmap:install \
    && php bin/console asset-map:compile \
    && php bin/console cache:warmup \
    && chown -R app: var

USER app
