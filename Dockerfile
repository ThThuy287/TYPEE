FROM wordpress:latest

# Cài đặt và bật extension Redis cho PHP; rsync dùng khi khởi động container
RUN pecl install redis && docker-php-ext-enable redis \
	&& apt-get update && apt-get install -y --no-install-recommends rsync \
	&& rm -rf /var/lib/apt/lists/*

# Tối ưu OPcache/realpath cho môi trường local
COPY docker/php/zz-typee-perf.ini /usr/local/etc/php/conf.d/zz-typee-perf.ini

# Plugin & theme bên thứ ba nằm sẵn trong image (không đọc từ ổ Windows mỗi lần tải trang).
# Sau khi thêm/cập nhật plugin trong thư mục dự án: chạy lại `docker compose up -d --build`.
COPY wp-content/ /usr/src/typee/wp-content/

COPY docker/typee-entrypoint.sh /usr/local/bin/typee-entrypoint.sh
RUN sed -i 's/\r$//' /usr/local/bin/typee-entrypoint.sh && chmod +x /usr/local/bin/typee-entrypoint.sh

ENTRYPOINT ["typee-entrypoint.sh"]
CMD ["apache2-foreground"]
