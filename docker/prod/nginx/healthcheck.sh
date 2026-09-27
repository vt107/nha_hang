#!/bin/sh
# nginx sống + Laravel trả lời /up (kiểm tra luôn php-fpm).
if [ "$(cat /etc/nginx/site-mode 2>/dev/null)" = "https" ]; then
    wget -q -O /dev/null --no-check-certificate --header "Host: ${SERVER_NAME}" https://127.0.0.1/up
else
    wget -q -O /dev/null http://127.0.0.1/up
fi
