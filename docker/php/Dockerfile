FROM php:8.4-fpm AS base

# 安裝基礎套件和 SQLite3 支援
RUN apt-get update && apt-get install -y \
    git \
    zip \
    unzip \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    sqlite3 \
    libsqlite3-dev

# 設定 GD 擴充功能和 SQLite
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd pdo pdo_sqlite

# 安裝 Redis 擴展
RUN pecl install redis \
    && docker-php-ext-enable redis

# 安裝 Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# 設定工作目錄
WORKDIR /var/www/html

# 複製所有專案檔案
COPY . .

# 設定 git 安全目錄
RUN git config --global --add safe.directory /var/www/html

# 安裝相依套件
RUN composer install --no-interaction --no-scripts --no-progress --prefer-dist

# 建立必要的目錄
RUN mkdir -p /var/www/html/storage/logs \
    && mkdir -p /var/www/html/storage/cache \
    && mkdir -p /var/www/html/storage/uploads

# 設定正確的權限
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html/storage \
    && find /var/www/html/database -name "*.db" -exec chmod 664 {} \; 2>/dev/null || true

# 建立測試環境的映像檔
FROM base AS test

# 安裝 Xdebug 和 PCNTL
RUN pecl install xdebug \
    && docker-php-ext-enable xdebug \
    && docker-php-ext-install pcntl

# 複製 Xdebug 設定
COPY docker/php/conf.d/xdebug.ini /usr/local/etc/php/conf.d/xdebug.ini

# 建立必要的目錄
RUN mkdir -p /var/www/html/storage/coverage \
    && mkdir -p /var/www/html/storage/logs \
    && mkdir -p /var/www/html/storage/cache \
    && chown -R www-data:www-data /var/www/html/storage
