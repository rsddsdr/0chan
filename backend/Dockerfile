FROM ubuntu:22.04

ARG DEBIAN_FRONTEND=noninteractive

RUN apt-get update && apt-get install -y software-properties-common

RUN LC_ALL=C.UTF-8 add-apt-repository ppa:ondrej/php -y

RUN apt-get install -y wget git php7.4-fpm php7.4-memcache php7.4-pgsql php7.4-redis php7.4-gd php7.4-mbstring php7.4-dom php7.4-imagick php7.4-curl php7.4-apcu

RUN mkdir -p /var/www/logs

WORKDIR /src

ADD ./composer.json composer.json
ADD ./config/get-composer.sh config/get-composer.sh

RUN sh config/get-composer.sh
RUN php composer.phar install --no-autoloader

COPY . ./

RUN php composer.phar install --optimize-autoloader --apcu-autoloader

RUN mkdir /images
RUN chmod 777 /images

ENTRYPOINT /src/config/docker-entrypoint.sh $(echo $ARGS)
