FROM php:8.2-apache
WORKDIR /var/www/html
RUN apt-get update && apt-get install -y git zip unzip && rm -rf /var/lib/apt/lists/*
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
COPY . .
RUN composer install --no-dev --optimize-autoloader --no-interaction --ignore-platform-reqs
RUN a2enmod rewrite
EXPOSE 80
CMD ["apache2-foreground"]
