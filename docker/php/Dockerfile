FROM php:8.4-fpm-alpine

# Runtime libraries
RUN apk add --no-cache \
    bash \
    curl \
    git \
    libpng \
    libjpeg-turbo \
    freetype \
    libzip \
    oniguruma \
    libxml2 \
    icu-libs \
    nodejs \
    npm \
    unzip

# Build dependencies + PHP extensions + cleanup
RUN apk add --no-cache --virtual .build-deps \
        $PHPIZE_DEPS \
        libpng-dev \
        libjpeg-turbo-dev \
        freetype-dev \
        libzip-dev \
        oniguruma-dev \
        libxml2-dev \
        icu-dev \
        linux-headers \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo \
        pdo_mysql \
        mbstring \
        exif \
        pcntl \
        bcmath \
        gd \
        zip \
        opcache \
        intl \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps

# Chromium untuk render laporan PDF (Browsershot).
#
# Chrome unduhan puppeteer di-build untuk glibc dan tidak jalan di Alpine (musl),
# jadi dipakai paket chromium bawaan Alpine dan Browsershot diarahkan ke situ.
# Paket npm `puppeteer` tetap perlu ada di node_modules karena browser.cjs milik
# browsershot me-require-nya — hanya unduhan Chrome-nya yang dimatikan.
RUN apk add --no-cache \
    chromium \
    nss \
    harfbuzz \
    ca-certificates \
    ttf-freefont \
    font-noto

ENV BROWSERSHOT_CHROME_PATH=/usr/bin/chromium-browser \
    PUPPETEER_SKIP_DOWNLOAD=true

# Composer dari image resmi
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Custom PHP config
COPY php.ini /usr/local/etc/php/conf.d/custom.ini

# Entrypoint untuk auto-setup
COPY entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

WORKDIR /var/www/kinerja

ENTRYPOINT ["entrypoint.sh"]
CMD ["php-fpm"]
