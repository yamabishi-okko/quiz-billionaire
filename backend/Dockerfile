# --------------------------------------------
# Dockerfile (PHP 8.2 + Apache)
# 役割:
#   - PHP + Apache の実行環境を作るレシピ
#   - Apache の rewrite 機能をONにし、.htaccess を有効にする
#   - /var/www/html にアプリを配置
# --------------------------------------------

# backend/Dockerfile
FROM php:8.2-apache

# PHP の PDO / MySQL 拡張を入れる（DB接続のため）
RUN docker-php-ext-install pdo pdo_mysql

# Apache の URL 書き換えモジュールを有効化
RUN a2enmod rewrite

# 仮想ホスト設定を上書き（.htaccess を効かせるための AllowOverride All）
COPY apache.vhost.conf /etc/apache2/sites-available/000-default.conf

# Web ドキュメントルート（Apacheがここを公開領域として見る）
WORKDIR /var/www/html

# アプリのファイル一式をコンテナにコピー
COPY . /var/www/html
