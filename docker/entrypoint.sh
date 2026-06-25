#!/bin/sh
set -eu

: "${DB_HOST:=db}"
: "${DB_PORT:=3306}"
: "${DB_DATABASE:=epay}"
: "${DB_USERNAME:=epay}"
: "${DB_PASSWORD:=epay_password}"
: "${DB_TABLE_PREFIX:=pay}"
: "${WRITE_CONFIG:=true}"

escape_php() {
  printf "%s" "$1" | sed "s/'/'\\\\''/g"
}

if [ "$WRITE_CONFIG" = "true" ]; then
  cat > /var/www/html/config.php <<PHP
<?php
/*数据库配置*/
\$dbconfig=array(
	'host' => '$(escape_php "$DB_HOST")', //数据库服务器
	'port' => $(escape_php "$DB_PORT"), //数据库端口
	'user' => '$(escape_php "$DB_USERNAME")', //数据库用户名
	'pwd' => '$(escape_php "$DB_PASSWORD")', //数据库密码
	'dbname' => '$(escape_php "$DB_DATABASE")', //数据库名
	'dbqz' => '$(escape_php "$DB_TABLE_PREFIX")' //数据表前缀
);
PHP
fi

chown www-data:www-data /var/www/html/config.php

exec docker-php-entrypoint "$@"
