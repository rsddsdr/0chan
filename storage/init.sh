#!/usr/bin/env bash
echo $STORAGE_ID > /storage/id
chown -R 33:33 /storage
sed -i 's/__SALT__/'"${SALT}"'/' /app/config/nginx.conf
supervisord -c /app/config/supervisord.conf
