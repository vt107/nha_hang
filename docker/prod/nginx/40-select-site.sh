#!/bin/sh
# Chọn cấu hình site khi nginx khởi động: có chứng chỉ cho SERVER_NAME thì HTTPS, không thì HTTP cổng 80.
# Nạp lại nginx định kỳ để nhận chứng chỉ certbot vừa gia hạn.
set -e

CERT="/etc/letsencrypt/live/${SERVER_NAME}/fullchain.pem"

if [ -n "${SERVER_NAME}" ] && [ -f "${CERT}" ]; then
    TEMPLATE=https
else
    TEMPLATE=http
fi

envsubst '${SERVER_NAME}' < "/etc/nginx/app-templates/${TEMPLATE}.conf.template" > /etc/nginx/conf.d/site.conf
echo "$TEMPLATE" > /etc/nginx/site-mode
echo "40-select-site: dùng cấu hình ${TEMPLATE} (SERVER_NAME=${SERVER_NAME:-chưa đặt})"

(while :; do sleep 6h; nginx -s reload; done) &
