FROM byjg/php:8.5-fpm-nginx-2026.06

# Refer to the documentation to setup the environment variables
# https://github.com/byjg/docker-php/blob/master/docs/environment.md
ENV NGINX_ROOT="/srv/public"
ENV PHP_CONTROLLER="/app.php"
ENV TZ=UTC
# ---------------------------------------------

WORKDIR /srv

# Setup Docker/Fpm

COPY ./docker/fpm/php /etc/php85/conf.d
COPY ./docker/nginx/conf.d /etc/nginx/http.d/

# Custom system-level installations
# The Docker image runs as non-root user `app` for security.
# Temporarily switch to root to install system packages, then switch back to `app`.
USER root
RUN apk add --no-cache --update tzdata

# Copy project files (the PHP application root is the repository root)
COPY --chown=app:app builder /srv/builder
COPY --chown=app:app config /srv/config
COPY --chown=app:app src /srv/src
COPY --chown=app:app public /srv/public
COPY --chown=app:app templates /srv/templates

# This is necessary for the migration script
COPY --chown=app:app composer.* /srv/

# Optional: Override app user UID/GID to match host user for volume permissions
# This is useful when mounting host directories as volumes to avoid permission issues.
# Pass USER_ID and GROUP_ID as build args to enable this feature.
# Example: docker-compose build --build-arg USER_ID=$(id -u) --build-arg GROUP_ID=$(id -g)
ARG USER_ID
ARG GROUP_ID
RUN if [ -n "$USER_ID" ]; then \
        find / -user app -exec chown ${USER_ID} {} + 2>/dev/null || true && \
        find / -group app -exec chgrp ${GROUP_ID} {} + 2>/dev/null || true && \
        deluser app && \
        addgroup -g ${GROUP_ID} app && \
        adduser -D -u ${USER_ID} -G app -s /bin/bash app && \
        chown -R app:app /run; \
    fi

USER app

RUN git config --global --add safe.directory /srv && \
    composer install --no-dev --no-interaction --no-progress --no-scripts --optimize-autoloader && \
    composer dump-autoload --optimize --classmap-authoritative -q
