FROM php:8.3-cli-alpine
RUN apk add --no-cache unzip
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --prefer-dist --no-interaction --no-progress --no-scripts
COPY . .
EXPOSE 8080
CMD ["sh", "docker-entrypoint.sh"]
