FROM php:7.2-apache

RUN apt-get update && apt-get install -y libpng-dev libjpeg-dev \
	&& rm -rf /var/lib/apt/lists/* \
	&& docker-php-ext-configure gd --with-png-dir=/usr --with-jpeg-dir=/usr \
	&& docker-php-ext-install gd \
	&& docker-php-ext-install mysqli

COPY build/docker/date-timezone.ini /usr/local/etc/php/conf.d/

COPY htdocs/ /var/www/html/

RUN chown -hR www-data:www-data /var/www/html

EXPOSE 80
