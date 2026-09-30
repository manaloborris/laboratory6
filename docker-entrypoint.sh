#!/bin/sh
set -eu

port="${PORT:-10000}"
sed -i "s/^Listen 80$/Listen ${port}/" /etc/apache2/ports.conf
sed -i "s/:80>/:${port}>/" /etc/apache2/sites-available/000-default.conf

if [ -n "${DB_SSL_CA:-}" ] && [ -f "$DB_SSL_CA" ]; then
	install -o www-data -g www-data -m 0440 "$DB_SSL_CA" /tmp/aiven-ca.pem
	export DB_SSL_CA=/tmp/aiven-ca.pem
fi

exec apache2-foreground