FROM php:8.2-apache

# Enable mysqli and pdo_mysql extensions
RUN docker-php-ext-install mysqli pdo_mysql

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Disable conflicting MPM modules and enable prefork
RUN a2dismod mpm_event mpm_worker || true && a2enmod mpm_prefork || true

# Copy project files
COPY . /var/www/html/

# Set working directory
WORKDIR /var/www/html

# Set permissions
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80
