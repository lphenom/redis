FROM php:8.1-alpine

# Install system dependencies
RUN apk add --no-cache \
    git \
    unzip \
    autoconf \
    g++ \
    make \
    linux-headers

# Install ext-redis
RUN pecl install redis-6.0.2 \
    && docker-php-ext-enable redis

# Install Composer (pinned version)
COPY --from=composer:2.9.5 /usr/bin/composer /usr/bin/composer

WORKDIR /app

CMD ["php", "-a"]

