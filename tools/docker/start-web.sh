#!/bin/bash
set -Eeuo pipefail

# Both masters receive graceful shutdown signals; Docker's init reaps orphans.
shutdown() {
    trap '' TERM INT QUIT
    kill -QUIT "$php_pid" "$nginx_pid" 2>/dev/null || true
    wait "$php_pid" 2>/dev/null || true
    wait "$nginx_pid" 2>/dev/null || true
}

nginx -t
php-fpm --test

php-fpm --nodaemonize &
php_pid=$!
nginx -g 'daemon off;' &
nginx_pid=$!

trap 'shutdown; exit 0' TERM INT QUIT

# If either server exits, stop its peer and fail the container.
status=0
wait -n "$php_pid" "$nginx_pid" || status=$?
shutdown
if [ "$status" -eq 0 ]; then status=1; fi
exit "$status"
