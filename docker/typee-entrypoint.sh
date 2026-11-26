#!/bin/sh
# TYPEE: chép plugin/theme bên thứ ba có sẵn trong image vào wp-content của container trước khi WordPress chạy.
# Lý do: đọc file từ ổ Windows gắn vào Docker chậm ~270 lần so với file trong container.
# Phần nhóm tự viết (typee-child, typee-core, mu-plugins, uploads, fonts) vẫn nối trực tiếp với máy (xem docker-compose.yml).
set -e
SRC=/usr/src/typee/wp-content
DST=/var/www/html/wp-content
mkdir -p "$DST/plugins" "$DST/themes" "$DST/languages"
rsync -a --delete --chown=www-data:www-data --exclude=/typee-core/ "$SRC/plugins/" "$DST/plugins/"
rsync -a --delete --chown=www-data:www-data --exclude=/typee-child/ "$SRC/themes/" "$DST/themes/"
rsync -a --delete --chown=www-data:www-data "$SRC/languages/" "$DST/languages/"
for f in object-cache.php index.php; do
	if [ -f "$SRC/$f" ]; then
		cp -p "$SRC/$f" "$DST/$f"
		chown www-data:www-data "$DST/$f"
	fi
done
exec docker-entrypoint.sh "$@"
