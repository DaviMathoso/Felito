#!/bin/sh
set -eu

php /var/www/html/docker/seed-admin.php
exec "$@"
