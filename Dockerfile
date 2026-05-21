FROM php:8.2-apache

# Install PostgreSQL client development libraries and configure PHP PDO PostgreSQL extensions
RUN apt-get update && apt-get install -y libpq-dev \
    && docker-php-ext-install pdo pdo_pgsql \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Copy the application source code into the Apache default document root
COPY src/ /var/www/html/

# Set correct permissions for Apache
RUN chown -R www-data:www-data /var/www/html/

# Expose port 80 to receive web traffic
EXPOSE 80
