FROM php:8.4-apache

RUN docker-php-ext-install pdo_mysql

COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf

WORKDIR /var/www/html
COPY app/ app/
COPY config/ config/
COPY public/ public/
COPY views/ views/
COPY html_Referencia/ html_Referencia/
COPY docker/ docker/

RUN chmod +x /var/www/html/docker/entrypoint.sh

ENTRYPOINT ["/var/www/html/docker/entrypoint.sh"]
CMD ["apache2-foreground"]

EXPOSE 80
