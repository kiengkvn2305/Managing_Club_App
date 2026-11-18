FROM php:8.4-cli-bookworm
RUN apt-get update && apt-get install -y --no-install-recommends git unzip libzip-dev libonig-dev libicu-dev libsqlite3-dev libxml2-dev \
    && docker-php-ext-install pdo_mysql pdo_sqlite bcmath mbstring zip intl dom xml xmlwriter pcntl \
    && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
WORKDIR /app
COPY . .
RUN composer install --prefer-dist --no-interaction --no-progress \
    && useradd --create-home --uid 1000 fcms \
    && chown -R fcms:fcms /app
USER fcms
EXPOSE 8000
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
