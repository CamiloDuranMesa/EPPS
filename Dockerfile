FROM php:8.2-apache

# 1. Instalar herramientas base y extensiones PHP necesarias
RUN apt-get update && apt-get install -y \
        curl \
        git \
        unzip \
        libzip-dev \
        libpng-dev \
        libjpeg62-turbo-dev \
        libfreetype6-dev \
        libwebp-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install \
        gd \
        mysqli \
        zip \
        fileinfo \
    && docker-php-ext-enable \
        gd \
        mysqli \
        zip \
        fileinfo \
    && rm -rf /var/lib/apt/lists/*

# 2. Instalar Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# 3. Habilitar el módulo de reescritura de Apache (útil para redirecciones)
RUN a2enmod rewrite

# 4. Configurar el directorio de trabajo y preparar dependencias
WORKDIR /var/www/html
COPY composer.json ./
RUN composer install --no-interaction --prefer-dist --optimize-autoloader

# 5. Copiar el resto del proyecto
COPY . /var/www/html/

# 6. Asegurar que Apache tenga los permisos correctos sobre la carpeta web
RUN chown -R www-data:www-data /var/www/html

# 7. Exponer el puerto estándar
EXPOSE 80
