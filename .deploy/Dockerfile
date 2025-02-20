FROM php:8.4-fpm
ARG WORKDIR=/var/www/html
ENV DOCUMENT_ROOT=${WORKDIR}
ENV USER=www-data
ENV TZ=UTC
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    libmemcached-dev \
    libzip-dev \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    librdkafka-dev \
    libpq-dev \
    openssh-server \
    zip \
    unzip \
    supervisor \
    sqlite3  \
    nano \
    cron
RUN apt-get update && apt-get install -y nginx
RUN apt-get clean && rm -rf /var/lib/apt/lists/*
RUN docker-php-ext-configure gd --with-freetype --with-jpeg
RUN docker-php-ext-install  zip mbstring exif pcntl bcmath -j$(nproc) gd intl
RUN docker-php-ext-install pdo_mysql
RUN docker-php-ext-install pdo_pgsql
RUN docker-php-ext-install opcache

RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && echo $TZ > /etc/timezone

RUN pecl install memcached && docker-php-ext-enable memcached
RUN pecl install xdebug

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
WORKDIR $WORKDIR

RUN rm -Rf /var/www/* && mkdir -p /var/www/html

ADD ./.deploy/config/default.conf /etc/nginx/sites-available/default
ADD ./.deploy/config/php.ini $PHP_INI_DIR/conf.d/
ADD ./.deploy/config/supervisord.conf /etc/supervisor/supervisord.conf
ADD ./.deploy/config/www.conf /usr/local/etc/php-fpm.conf

COPY ./.deploy/entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/entrypoint.sh
COPY . /var/www/html

RUN chmod -R 755 $WORKDIR
RUN chown -R www-data:www-data $WORKDIR
EXPOSE 80
#USER www-data
ENTRYPOINT ["entrypoint.sh"]
CMD [ "entrypoint" ]
