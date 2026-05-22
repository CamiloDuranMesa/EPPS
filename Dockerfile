FROM php:8.2-apache

# 1. Instalar la extensión mysqli obligatoria para conectar con MySQL
RUN docker-php-ext-install mysqli && docker-php-ext-enable mysqli

# 2. Habilitar el módulo de reescritura de Apache (útil para redirecciones)
RUN a2enmod rewrite

# 3. Asegurar que Apache tenga los permisos correctos sobre la carpeta web
RUN chown -R www-data:www-data /var/www/html

# 4. Copiar todo el código de tu proyecto a la carpeta de Apache
COPY . /var/www/html/

# 5. Exponer el puerto estándar
EXPOSE 80
