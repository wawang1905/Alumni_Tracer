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
       mysqli pdo_mysql pgsql pdo_pgsql intl mbstring zip gd \
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

# Allow larger photo/document uploads (default PHP limit is 2M)
RUN printf "upload_max_filesize = 20M\npost_max_size = 25M\n" > /usr/local/etc/php/conf.d/uploads.ini

# Set writable permissions (including public/uploads/news for news photo uploads)
RUN mkdir -p writable/cache writable/logs writable/session writable/uploads/profile_pictures writable/uploads/survey_answers writable/backups writable/debugbar public/uploads/news \
    && chown -R www-data:www-data writable public/uploads \
    && chmod -R 775 writable public/uploads

EXPOSE 10000

ENTRYPOINT ["php", "-r", "$port = getenv('PORT') ?: '10000'; foreach (['/etc/apache2/ports.conf', '/etc/apache2/sites-available/000-default.conf'] as $f) { $c = file_get_contents($f); $c = preg_replace(['/Listen\\s+\\d+/', '/<VirtualHost\\s+\\*:\\d+>/'], ['Listen ' . $port, '<VirtualHost *:' . $port . '>'], $c); file_put_contents($f, $c); } $envVarsLines = ''; $dotEnvLines = ''; foreach (getenv() as $k => $v) { if (strpos($k, '.') !== false || strpos($k, 'database_') === 0 || strpos($k, 'supabase_') === 0 || strpos($k, 'email_') === 0 || strpos($k, 'app_') === 0 || $k === 'CI_ENVIRONMENT') { $uk = preg_replace('/[^A-Za-z0-9_]/', '_', $k); putenv($uk . '=' . $v); $envVarsLines .= 'export ' . $uk . '=' . escapeshellarg((string) $v) . \"\\n\"; $safeVal = str_replace([\"\\r\", \"\\n\", \"'\"], ['', '', \"\\'\"], (string) $v); $dotEnvLines .= $k . \" = '\" . $safeVal . \"'\\n\"; if ($uk !== $k) { $dotEnvLines .= $uk . \" = '\" . $safeVal . \"'\\n\"; } } } if ($envVarsLines !== '') { file_put_contents('/etc/apache2/envvars', \"\\n\" . $envVarsLines, FILE_APPEND); } if (!file_exists('/var/www/html/.env') && $dotEnvLines !== '') { file_put_contents('/var/www/html/.env', $dotEnvLines); chown('/var/www/html/.env', 'www-data'); chmod('/var/www/html/.env', 0600); } passthru('apache2-foreground', $code); exit($code);"]
CMD []
