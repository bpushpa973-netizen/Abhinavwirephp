FROM php:8.3-cli
WORKDIR /app
RUN docker-php-ext-install pdo_mysql
COPY . /app
ENV APP_ENV=production
EXPOSE 8080
CMD ["sh", "-c", "php -S 0.0.0.0:${PORT:-8080} router.php"]
