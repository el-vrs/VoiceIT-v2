FROM php:8.2-cli

# Install mysqli extension
RUN docker-php-ext-install mysqli pdo_mysql

# Set working directory
WORKDIR /app

# Copy project files
COPY . /app/

EXPOSE 8080

CMD php -S 0.0.0.0:8080 -t /app
