
# Official PHP image via the AWS ECR Public mirror of Docker Hub's library.
# Same image, but builds no longer depend on Docker Hub's anonymous pull
# rate limit, which shared build hosts hit often ("toomanyrequests").
FROM public.ecr.aws/docker/library/php:8.2-apache

# Install required system packages
RUN apt-get update && apt-get install -y --no-install-recommends \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    libzip-dev \
    libpq-dev \
    zip \
    unzip \
    postgresql-client \
    default-mysql-client \
    && rm -rf /var/lib/apt/lists/*

# Configure and install PHP extensions (includes pdo_pgsql for Render PostgreSQL)
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd pdo pdo_mysql pdo_pgsql zip bcmath

# Enable Apache modules required for routing, headers and reverse proxies
RUN a2enmod rewrite headers remoteip

# Copy custom PHP settings
COPY docker/php.ini $PHP_INI_DIR/conf.d/custom.ini

# Copy Apache VirtualHost configuration
COPY docker/vhost.conf /etc/apache2/sites-available/000-default.conf

# Install Composer (official installer, Composer 2)
RUN curl -sS https://getcomposer.org/installer | php -- --2 --install-dir=/usr/bin --filename=composer

# Set working directory
WORKDIR /var/www/html

# Copy application codebase
COPY . /var/www/html

# Install production PHP dependencies
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Setup entrypoint script (and normalize line endings)
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN sed -i 's/\r$//' /usr/local/bin/docker-entrypoint.sh \
    && chmod +x /usr/local/bin/docker-entrypoint.sh

# Expose web ports
EXPOSE 80 10000

ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["apache2-foreground"]
