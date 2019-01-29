FROM php:7.2.9-fpm-alpine3.8

MAINTAINER christian.nilsson@digitalistgroup.com

RUN apk update && apk upgrade && apk add --no-cache bash geoip geoip-dev libjpeg-turbo libjpeg-turbo-dev freetype freetype-dev libpng libpng-dev libldap openldap-dev && \ 
  apk add --no-cache --virtual .build-deps $PHPIZE_DEPS && \
  pecl install igbinary geoip-1.1.1 && \
  yes "\n" | pecl install apcu && \
  yes "\n" | pecl install redis-3.1.6 && \
  rm -rf /tmp/pear && \
  docker-php-ext-configure gd \
      --with-gd \
      --with-freetype-dir=/usr/include/ \
      --with-png-dir=/usr/include/ \
      --with-jpeg-dir=/usr/include/ && \
  NPROC=$(grep -c ^processor /proc/cpuinfo 2>/dev/null || 1) && \
  docker-php-ext-install -j${NPROC} gd && \
  docker-php-ext-install ldap && \
  docker-php-ext-install pdo_mysql && \
  docker-php-ext-enable igbinary && \
  docker-php-ext-enable redis && \
  docker-php-ext-enable apcu && \
  docker-php-ext-enable geoip && \
  docker-php-ext-enable opcache && \
  apk add --no-cache supervisor && \
  apk del --no-cache .build-deps 

COPY ./.docker/php/docker-php-ext-geoip.ini /usr/local/etc/php/conf.d/docker-php-ext-geoip.ini
COPY ./.docker/php/docker-php-general.ini /usr/local/etc/php/conf.d/docker-php-general.ini
COPY ./app /usr/src/piwik
COPY ./.docker/matomo /scripts
COPY docker-entrypoint.sh /entrypoint.sh

# WORKDIR is /var/www/html (inherited via "FROM php")
# "/entrypoint.sh" will populate it at container startup from /usr/src/piwik
# Hopefully, this can be useful using k8s emptydir to share this with nginx in
# the same pod, offloading static files

VOLUME /var/www/html

ENTRYPOINT ["/entrypoint.sh"]
CMD ["php-fpm"]
