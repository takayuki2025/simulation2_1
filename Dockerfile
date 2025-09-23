FROM php:8.4-fpm

# 必要なシステムパッケージとPHP拡張機能をインストール
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libzip-dev \
    libonig-dev \
    libicu-dev \
    libexif-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    mariadb-client \
    zip \
    unzip \
    && rm -rf /var/lib/apt/lists/* \
    && docker-php-ext-install -j$(nproc) pdo_mysql mbstring intl exif zip gd

# Composerをグローバルにインストール
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# wait-for-it.shスクリプトをダウンロードし、実行権限を付与
RUN curl -L https://github.com/vishnubob/wait-for-it/raw/master/wait-for-it.sh -o /usr/local/bin/wait-for-it.sh \
    && chmod +x /usr/local/bin/wait-for-it.sh

# 作業ディレクトリの設定とユーザーの変更
WORKDIR /var/www
USER www-data

# コンテナ起動時にPHP-FPMを実行
CMD ["php-fpm"]