# =======================================================
# Dockerfile para Despliegue en Render (PHP 8.2 + Apache)
# =======================================================

FROM php:8.2-apache

# 1. Instalar dependencias del sistema requeridas por PHP y Composer
RUN apt-get update && apt-get install -y --no-install-recommends \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    libzip-dev \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# 2. Habilitar módulo mod_rewrite de Apache y configurar AllowOverride
RUN a2enmod rewrite \
    && sed -ri -e 's!/var/www/html!/var/www/html!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!/var/www/html!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf \
    && echo '<Directory /var/www/html>\n    Options Indexes FollowSymLinks\n    AllowOverride All\n    Require all granted\n</Directory>' >> /etc/apache2/apache2.conf

# 3. Copiar Composer oficial desde la imagen de Docker Hub
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# 4. Establecer directorio de trabajo
WORKDIR /var/www/html

# 5. Copiar los archivos del proyecto al contenedor
COPY . /var/www/html

# 6. Instalar dependencias de Composer optimizadas para producción
RUN composer install --no-dev --optimize-autoloader --no-interaction

# 7. Asignar permisos al usuario de Apache
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# 8. Exponer el puerto 80 por defecto
EXPOSE 80

# 9. Iniciar Apache adaptándose al puerto dinámico de Render ($PORT) o al puerto 80
CMD ["sh", "-c", "sed -i \"s/Listen 80/Listen ${PORT:-80}/g\" /etc/apache2/ports.conf && sed -i \"s/:80/:${PORT:-80}/g\" /etc/apache2/sites-available/000-default.conf && apache2-foreground"]
