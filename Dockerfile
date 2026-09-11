# Imagen única de la aplicación: PHP 8.3 sobre Apache.
#
# No hay paso de compilación de PHP, así que una sola etapa basta. El CSS se
# compila aparte con `npm run build` y el resultado se versiona, de modo que la
# imagen no necesita Node para funcionar.
FROM php:8.3-apache

# pdo_mysql es la única extensión que la aplicación necesita además del núcleo.
RUN docker-php-ext-install pdo_mysql \
 && a2enmod rewrite \
 && rm -rf /var/lib/apt/lists/*

# El documento raíz es public/: el resto del código queda fuera del alcance web.
COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf
COPY docker/php.ini /usr/local/etc/php/conf.d/aplicacion.ini

WORKDIR /var/www/html
COPY . .

# Solo el directorio de registros necesita escritura.
RUN chown -R www-data:www-data storage \
 && chmod -R 755 storage

COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# La plataforma puede asignar otro puerto por la variable PORT; el entrypoint
# reconfigura Apache en el arranque. 80 es solo el valor por defecto.
ENV PORT=80
EXPOSE 80

ENTRYPOINT ["entrypoint.sh"]
CMD ["apache2-foreground"]
