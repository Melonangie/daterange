FROM php:7-fpm-alpine

# PDO
RUN docker-php-ext-install pdo pdo_mysql

# Redis
RUN apk add --no-cache --virtual .build-deps g++ make autoconf
RUN pecl install redis-5.0.2 && docker-php-ext-enable redis

# Time zone
RUN apk add --no-cache tzdata
ENV TZ America/Tijuana

## Composer
#RUN curl -sS https://getcomposer.org/installer | php && mv composer.phar /usr/local/bin/composer
#COPY /api /api
#WORKDIR /api
#RUN composer install --prefer-dist --no-scripts --no-dev --no-autoloader && rm -rf /root/.composer
#RUN composer dump-autoload --no-scripts --no-dev --optimize
