# =============================================================================
# sysPass - Multi-stage Dockerfile
# =============================================================================
# Stage 1: Builder - Install Composer dependencies
# =============================================================================
FROM composer:2.7 AS builder

WORKDIR /build

# Copy only dependency manifests first for layer caching
COPY composer.json composer.lock ./

# Install production dependencies only (no dev)
# Ignore platform reqs for gd/gettext since they're available in production stage
RUN composer install \
    --no-dev \
    --no-interaction \
    --no-progress \
    --no-scripts \
    --prefer-dist \
    --optimize-autoloader \
    --ignore-platform-req=ext-gd \
    --ignore-platform-req=ext-gettext

# =============================================================================
# Stage 2: Production - PHP 8.2 Apache
# =============================================================================
FROM php:8.2-apache-bookworm

LABEL maintainer="sysPass Team <nuxsmin@syspass.org>" \
      description="sysPass Password Manager" \
      version="3.x"

# ---------------------------------------------------------------------------
# System dependencies & PHP extensions
# ---------------------------------------------------------------------------
RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends \
        libcurl4-openssl-dev \
        libfreetype6-dev \
        libjpeg62-turbo-dev \
        libpng-dev \
        libgd-dev \
        libonig-dev \
        libxml2-dev \
        libzip-dev \
        zlib1g-dev \
        gettext \
        unzip \
        curl \
    ; \
    # Configure gd with freetype and jpeg
    docker-php-ext-configure gd --with-freetype --with-jpeg; \
    # Install PHP extensions (only those that need compilation)
    docker-php-ext-install -j$(nproc) \
        gd \
        gettext \
        mbstring \
        pdo_mysql \
        zip \
        opcache \
    ; \
    # Enable Apache modules
    a2enmod rewrite headers expires; \
    # Install runtime libraries that gd.so depends on (prevent autoremove from purging them)
    apt-get install -y --no-install-recommends \
        libfreetype6 \
        libjpeg62-turbo \
        libpng16-16 \
        libgd3 \
        libonig5 \
        libzip4 \
    ; \
    # Cleanup: purge -dev headers only, runtime libs stay
    apt-get purge -y libcurl4-openssl-dev libfreetype6-dev libjpeg62-turbo-dev \
        libpng-dev libgd-dev libonig-dev libxml2-dev libzip-dev zlib1g-dev; \
    apt-get autoremove -y; \
    apt-get clean; \
    rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

# ---------------------------------------------------------------------------
# Copy custom Apache virtual host config
# ---------------------------------------------------------------------------
COPY docker/000-default.conf /etc/apache2/sites-available/000-default.conf

# ---------------------------------------------------------------------------
# Copy production PHP configuration
# ---------------------------------------------------------------------------
COPY docker/php.ini /usr/local/etc/php/php.ini

# ---------------------------------------------------------------------------
# Copy application code
# ---------------------------------------------------------------------------
COPY . /var/www/html/

# ---------------------------------------------------------------------------
# Copy vendor from builder stage
# ---------------------------------------------------------------------------
COPY --from=builder /build/vendor /var/www/html/vendor

# ---------------------------------------------------------------------------
# Set proper permissions
# ---------------------------------------------------------------------------
RUN set -eux; \
    chown -R www-data:www-data /var/www/html; \
    chmod -R 755 /var/www/html; \
    mkdir -p /var/www/html/app/cache /var/www/html/app/config \
        /var/www/html/app/backup /var/www/html/app/temp; \
    chmod 750 /var/www/html/app/config; \
    chmod 770 /var/www/html/app/cache /var/www/html/app/backup /var/www/html/app/temp

# ---------------------------------------------------------------------------
# Health check
# ---------------------------------------------------------------------------
HEALTHCHECK --interval=30s --timeout=5s --start-period=10s --retries=3 \
    CMD curl -f http://localhost/ || exit 1

# ---------------------------------------------------------------------------
# Expose port
# ---------------------------------------------------------------------------
EXPOSE 80

# ---------------------------------------------------------------------------
# Start Apache
# ---------------------------------------------------------------------------
CMD ["apache2-foreground"]