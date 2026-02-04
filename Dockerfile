# -------------------------
# Build Stage
# -------------------------
FROM php:8.2-fpm AS build

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git zip unzip libpq-dev \
    && docker-php-ext-install pdo pdo_mysql pdo_pgsql

# Install composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Copy composer files first
COPY composer.json composer.lock ./

# Install dependencies
RUN composer install --no-dev --no-interaction --prefer-dist --no-scripts

# Copy full project
COPY . .

# Optimize autoload
RUN composer dump-autoload --optimize

# -------------------------
# Production Stage
# -------------------------
FROM php:8.2-fpm

# Install Nginx + Supervisor
RUN apt-get update && apt-get install -y \
    nginx supervisor libpq-dev \
    && docker-php-ext-install pdo pdo_mysql pdo_pgsql

WORKDIR /var/www/html

# Copy from build stage
COPY --from=build /var/www/html /var/www/html

# Copy Nginx & Supervisor configs
COPY ./deploy/nginx.conf /etc/nginx/nginx.conf
COPY ./deploy/supervisor.conf /etc/supervisor/conf.d/supervisor.conf

# Set permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Expose Render default port
EXPOSE 10000

# Start Supervisor to run PHP-FPM + Nginx
CMD ["/usr/bin/supervisord"]
