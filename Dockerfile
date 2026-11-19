
FROM php:8.2-apache

# Install system dependencies, Python, and PHP extensions
RUN apt-get update && apt-get install -y \
    git unzip curl \
    python3 python3-venv python3-dev \
    libicu-dev libzip-dev libpq-dev \
    libonig-dev libpng-dev libjpeg62-turbo-dev \
    libfreetype6-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
       mysqli pdo_mysql pdo_pgsql intl mbstring zip gd \
    && a2enmod rewrite \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Set application directory
WORKDIR /var/www/html

# Copy project
COPY . .

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Install Python dependencies
RUN python3 -m venv /opt/venv
ENV PATH="/opt/venv/bin:$PATH"

RUN if [ -f requirements.txt ]; then \
    pip install --no-cache-dir -r requirements.txt; \
    fi

# Configure Apache to use CodeIgniter public directory
ENV PORT=10000

RUN sed -i 's/Listen 80/Listen 10000/' /etc/apache2/ports.conf \
    && printf '<VirtualHost *:10000>\n\
    DocumentRoot /var/www/html/public\n\
    <Directory /var/www/html/public>\n\
        Options FollowSymLinks\n\
        AllowOverride All\n\
        Require all granted\n\
    </Directory>\n\
    ErrorLog /dev/stderr\n\
    CustomLog /dev/stdout combined\n\
</VirtualHost>\n' > /etc/apache2/sites-available/000-default.conf

# Set writable permissions
RUN mkdir -p writable/cache writable/logs writable/session writable/uploads writable/backups writable/debugbar \
    && chown -R www-data:www-data writable \
    && chmod -R 775 writable

EXPOSE 10000

CMD ["/bin/sh", "-c", "sed -i \"s/Listen .*/Listen ${PORT:-10000}/\" /etc/apache2/ports.conf && sed -i \"s/<VirtualHost \\*:[0-9]*>/<VirtualHost *:${PORT:-10000}>/\" /etc/apache2/sites-available/000-default.conf && exec apache2-foreground"]
