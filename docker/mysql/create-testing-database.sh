#!/usr/bin/env bash

set -euo pipefail

testing_database="${MYSQL_TEST_DATABASE:-eventradar_testing}"

if [[ ! "$testing_database" =~ ^[a-zA-Z0-9_]+$ ]]; then
    echo "Invalid MySQL testing database name: $testing_database" >&2
    exit 1
fi

MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysql --protocol=socket --user=root <<SQL
CREATE DATABASE IF NOT EXISTS \`$testing_database\`
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;
GRANT ALL PRIVILEGES ON \`$testing_database\`.* TO '$MYSQL_USER'@'%';
FLUSH PRIVILEGES;
SQL
