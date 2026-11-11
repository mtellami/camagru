#!/bin/sh
# Apache writes lifecycle notices to file logs even at LogLevel warn.
# Filter only known normal lifecycle IDs; retain every other diagnostic.
while IFS= read -r line; do
    message=${line#*] }
    case "$message" in
        "[mpm_prefork:notice]"*"AH00163:"*|"[core:notice]"*"AH00094:"*|"[mpm_prefork:notice]"*"AH00169:"*|"[mpm_prefork:notice]"*"AH00170:"*|"[mpm_prefork:notice]"*"AH00171:"*) ;;
        *) printf '%s\n' "$line" >> /var/www/html/storage/logs/apache-error.log ;;
    esac
done
