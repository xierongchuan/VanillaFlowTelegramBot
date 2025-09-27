# FROM php:8.4.12-fpm

# # 1) Системные библиотеки для сборки расширений
# RUN apt-get update -y

# RUN apt-get install -y \
#   git unzip zip \
#   libzip-dev libonig-dev libxml2-dev \
#   libpng-dev libjpeg-dev libfreetype6-dev \
#   libmemcached-tools \
#   libpq-dev postgresql-client

# RUN apt-get clean && rm -rf /var/lib/apt/lists/*

# # 2) PHP-расширения: PostgreSQL, GD и т.д.
# RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
#   && docker-php-ext-install -j$(nproc) \
#   mbstring exif pcntl bcmath zip \
#   pdo_pgsql gd \
#   && docker-php-ext-install pgsql

# # 3) Redis через PECL
# RUN pecl install redis-6.2.0 \
#   && docker-php-ext-enable redis

# # 4) Composer
# COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# WORKDIR /var/www/src_vanilla_flow_telegram_bot_api

# # 5) Копирование composer.json и composer.lock в контейнер
# COPY composer.json composer.lock ./

# # 6) Копирование кодовой базы в контейнер
# COPY . .

# # 7) Даём www-data право писать в storage и cache
# RUN chown -R www-data:www-data storage storage/framework bootstrap/cache && \
#   chmod -R 755 storage storage/framework bootstrap/cache

# # 8) Документирование порта
# EXPOSE 9000

# # 9) Запуск контейнера
# CMD ["bash", "-lc", "/wait-for-it.sh postgres:5432 --timeout=30 --strict -- composer install --optimize-autoloader --no-dev --no-scripts && php artisan migrate && exec php-fpm -F"]













# ---------------- STAGE: build ----------------
FROM php:8.4.12-fpm AS build

# 1) Системные библиотеки для сборки расширений
RUN apt-get update -y \
  && apt-get install -y \
  git unzip zip \
  libzip-dev libonig-dev libxml2-dev \
  libpng-dev libjpeg-dev libfreetype6-dev \
  libmemcached-tools \
  libpq-dev postgresql-client \
  && apt-get clean \
  && rm -rf /var/lib/apt/lists/*

# 2) PHP-расширения: PostgreSQL, GD и т.д.
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
  && docker-php-ext-install -j$(nproc) \
  mbstring exif pcntl bcmath zip \
  pdo_pgsql gd \
  && docker-php-ext-install pgsql

# 3) Redis через PECL
RUN pecl install redis-6.2.0 \
  && docker-php-ext-enable redis

WORKDIR /var/www/src_vanilla_flow_telegram_bot_api

# Скачиваем установщик Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/bin --filename=composer \
  && chmod +x /usr/bin/composer

# 5) Копирование исходного кода
COPY . .

# 4) Копирование composer файлы и установка зависимостей
COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader --prefer-dist

# 6) Применение миграций, кэширование (в build, для готового артефакта)
RUN php artisan config:cache \
  && php artisan route:cache
# && php artisan view:cache

# ---------------- STAGE: runtime ----------------
FROM php:8.4.12-fpm AS runner

# Устанавливаем только минимальные зависимости, если нужны
RUN apt-get update -y \
  && apt-get install -y \
  libpng-dev libjpeg-dev libfreetype6-dev libzip-dev libonig-dev libxml2-dev \
  libpq-dev \
  && apt-get clean \
  && rm -rf /var/lib/apt/lists/*

# (если нужно) включение расширений, которые были включены в build
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
  && docker-php-ext-install -j$(nproc) \
  pdo_pgsql gd \
  && pecl install redis-6.2.0 \
  && docker-php-ext-enable redis \
  && docker-php-ext-install zip mbstring

WORKDIR /var/www/src_vanilla_flow_telegram_bot_api

# Копируем из build стадии всё, что нужно для запуска
COPY --from=build /var/www/src_vanilla_flow_telegram_bot_api /var/www/src_vanilla_flow_telegram_bot_api

# Права на каталоги
RUN chown -R www-data:www-data storage bootstrap/cache \
  && chmod -R 755 storage bootstrap/cache

# Установка переменных окружения
# ENV LOG_CHANNEL=stderr

EXPOSE 9000

# Запуск
CMD ["php-fpm", "-F"]
