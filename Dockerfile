# syntax=docker/dockerfile:1
FROM php:7.4-apache

# single-app default VirtualHost
ENV APACHE_DOCUMENT_ROOT /var/www/plantnet-publish/web
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

# DEB packages
RUN curl -fsSL https://deb.nodesource.com/setup_10.x | bash - ; apt-get update; apt-get install -y imagemagick libmagickwand-dev tidy libtidy-dev libzip-dev git varnish nodejs=10.24.1-deb-1nodesource1 unzip libssl-dev libcurl4-openssl-dev openjdk-17-jre sudo

# PHP extensions
RUN printf "\n" | pecl install imagick; docker-php-ext-enable imagick; pecl install mongodb; docker-php-ext-enable mongodb; docker-php-ext-install exif gd gettext intl pcntl tidy zip opcache

# other tools
RUN curl -sS https://getcomposer.org/installer -o composer-setup.php; php composer-setup.php --version=2.2.9 --install-dir=/usr/local/bin --filename=composer; rm composer-setup.php; npm install -g less; ln -s /usr/bin/node /usr/bin/nodejs; ln -s /usr/lib/node_modules /usr/local/lib/node_modules

RUN a2enmod rewrite; service apache2 restart

COPY ./ /var/www/plantnet-publish/

WORKDIR /var/www/plantnet-publish

RUN chown www-data:www-data -R .

USER www-data

# fix directories and permissions
RUN mkdir app/cache; mkdir app/logs; mkdir -p web/media/cache; mkdir web/banners; mkdir web/uploads

RUN composer install

# config using env vars
ENTRYPOINT ["./docker-entrypoint.sh"]

RUN php app/console assets:install --symlink && php app/console assetic:dump --env=prod

# allow to exec a root bash inside the running container
USER root

EXPOSE 80
CMD ["apache2-foreground"]
