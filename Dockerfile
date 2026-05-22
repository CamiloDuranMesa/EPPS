FROM php:8.2-apache

# Instalar la extensión mysqli obligatoria para conectar con MySQL
RUN docker-php-ext-install mysqli && docker-php-ext-enable mysqli

# Copiar todo el código de tu proyecto al directorio web del servidor
COPY . /var/www/html/

# Exponer el puerto estándar de Apache
EXPOSE 80
