#!/usr/bin/env bash
sed -i 's/__ADMIN_LOGIN__/'"${ADMIN_LOGIN}"'/' ./seed.php
sed -i 's/__ADMIN_PASSWD__/'"${ADMIN_PASSWD}"'/' ./seed.php
php seed.php