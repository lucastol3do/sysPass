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
RUN composer install \
    --no-dev \
    --no-interaction \
    --no-progress \
    --no-scripts \
    --prefer-dist \
    --optimize-autoloader

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
        libgd-dev \
        libonig-dev \
        libxml2-dev \
        libzip-dev \
        zlib1g-dev \
        gettext \
        unzip \
    ; \
    docker-php-ext-install -j$(nproc) \
        curl \
        dom \
        fileinfo \
        gd \
        gettext \
        json \
        libxml \
        mbstring \
        pdo \
        pdo_mysql \
        phar \
        zip \
    ; \
    # Enable Apache modules
    a2enmod rewrite headers expires; \
    # Cleanup
    apt-get clean; \
    rm -rf /var/lib/apt/lists/*

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
    # Writable directories for runtime
    chmod -R 775 /var/www/html/app/cache; \
    chmod -R 775 /var/www/html/app/config; \
    chmod -R 775 /var/www/html/app/backup; \
    chmod -R 775 /var/www/html/app/temp

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
