# syntax=docker/dockerfile:1.7

# ============================================================
# Stage 1: بناء الـ frontend assets (Vite + Tailwind)
# ============================================================
FROM node:20-alpine AS frontend

WORKDIR /app

# ننسخ ملفات الـ package الأول عشان نستفيد من Docker layer caching
# لو package.json ما اتغيرش، Docker هيستخدم الـ layer المخزنة
COPY package.json package-lock.json* ./

# نثبت الـ dependencies
# لو فيه package-lock.json نستخدم npm ci (أسرع وأدق)
# لو مش موجود، نرجع لـ npm install
RUN if [ -f package-lock.json ]; then npm ci; else npm install; fi

# ننسخ ملفات الـ build والإعدادات
COPY vite.config.js ./
COPY tailwind.config.js* ./
COPY postcss.config.js* ./
COPY resources ./resources

# نبني الـ assets (tailwind + vite → public/build)
RUN npm run build


# ============================================================
# Stage 2: تركيب PHP dependencies بـ Composer
# ============================================================
FROM composer:2 AS vendor

WORKDIR /app

# ننسخ ملفات composer بس عشان layer caching
COPY composer.json composer.lock* ./

# نثبت الـ production dependencies فقط
# --no-scripts: مش هنشغّل artisan هنا (لسه مفيش كود)
# --no-dev: مش محتاجين phpunit و faker في production
# --optimize-autoloader: autoloader أسرع
RUN composer install \
    --no-dev \
    --no-interaction \
    --no-scripts \
    --no-progress \
    --prefer-dist \
    --optimize-autoloader


# ============================================================
# Stage 3: الصورة النهائية (PHP-FPM runtime)
# ============================================================
FROM php:8.2-fpm AS runtime

# ------------------------------------------------------------
# (1) تثبيت system libraries اللي PHP extensions محتاجينها
# ------------------------------------------------------------
RUN apt-get update && apt-get install -y --no-install-recommends \
        git \
        unzip \
        libzip-dev \
        libpng-dev \
        libjpeg62-turbo-dev \
        libfreetype6-dev \
        libonig-dev \
        libxml2-dev \
        libicu-dev \
        libpq-dev \
    && rm -rf /var/lib/apt/lists/*

# ------------------------------------------------------------
# (2) تركيب PHP extensions المطلوبة لـ Laravel
# ------------------------------------------------------------
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo_mysql \
        mbstring \
        zip \
        exif \
        pcntl \
        bcmath \
        gd \
        intl \
        opcache

# ------------------------------------------------------------
# (3) إعدادات OPcache للـ production
# ------------------------------------------------------------
RUN { \
        echo 'opcache.enable=1'; \
        echo 'opcache.enable_cli=0'; \
        echo 'opcache.memory_consumption=128'; \
        echo 'opcache.interned_strings_buffer=8'; \
        echo 'opcache.max_accelerated_files=10000'; \
        echo 'opcache.validate_timestamps=0'; \
        echo 'opcache.save_comments=1'; \
        echo 'opcache.fast_shutdown=1'; \
    } > /usr/local/etc/php/conf.d/opcache.ini

# ------------------------------------------------------------
# (4) PHP-FPM production tuning
# ------------------------------------------------------------
RUN { \
        echo '[global]'; \
        echo 'daemonize = no'; \
        echo 'error_log = /proc/self/fd/2'; \
        echo '[www]'; \
        echo 'access.log = /proc/self/fd/2'; \
        echo 'catch_workers_output = yes'; \
        echo 'decorate_workers_output = no'; \
    } > /usr/local/etc/php-fpm.d/zz-docker.conf

# ------------------------------------------------------------
# (5) Composer binary (مفيد للأوامر اليدوية داخل الـ container)
# ------------------------------------------------------------
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# ------------------------------------------------------------
# (6) نسخ كود المشروع
# (بفضل .dockerignore، node_modules و vendor مش هيتبعتوا)
# ------------------------------------------------------------
COPY . .

# ------------------------------------------------------------
# (7) نسخ الـ artifacts الجاهزة من Stages 1 & 2
# ------------------------------------------------------------
COPY --from=vendor /app/vendor ./vendor
COPY --from=frontend /app/public/build ./public/build

# ------------------------------------------------------------
# (8) تجهيز المجلدات المطلوبة للـ storage
# ------------------------------------------------------------
RUN mkdir -p \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
        storage/app/public \
        bootstrap/cache

# ------------------------------------------------------------
# (9) الصلاحيات: www-data لازم يملك الملفات القابلة للكتابة
# ------------------------------------------------------------
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 775 storage bootstrap/cache \
    && chmod -R 755 public

# ------------------------------------------------------------
# (10) تشغيل Laravel package discovery (يولّد bootstrap/cache/packages.php)
# ------------------------------------------------------------
RUN php artisan package:discover --ansi || true

# ------------------------------------------------------------
# (11) التبديل لمستخدم www-data (مش root — أفضل أمنيًا)
# ------------------------------------------------------------
USER www-data

EXPOSE 9000

CMD ["php-fpm"]
