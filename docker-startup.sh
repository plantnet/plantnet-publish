#!/bin/bash

set -e

echo "This is plantnet-publish startup script!"

function set_parameter {
    if [ -n "$2" ]; then
        echo "parameters.yml: setting \"$1\" to $2"
        sed -i -E "s/$1: .+/$1: $2/" app/config/parameters.yml
    fi
}

# config
set_parameter "locale" "$PUBLISH_SYMFONY_LOCALE"
set_parameter "secret" "$PUBLISH_SYMFONY_SECRET"

set_parameter "mdb_base" "$PUBLISH_MONGO_DATABASE"
set_parameter "mdb_username" "$PUBLISH_MONGO_USERNAME"
set_parameter "mdb_password" "$PUBLISH_MONGO_PASSWORD"

set_parameter "mailer_transport" "$PUBLISH_MAILER_TRANSPORT"
set_parameter "mailer_host" "$PUBLISH_MAILER_HOST"
set_parameter "mailer_user" "$PUBLISH_MAILER_USER"
set_parameter "mailer_password" "$PUBLISH_MAILER_PASSWORD"
set_parameter "from_email_adress" "$PUBLISH_MAILER_FROM_EMAIL_ADDRESS"
set_parameter "from_email_sender_name" "$PUBLISH_MAILER_FROM_SENDER_NAME"
set_parameter "mailer_disable" "$PUBLISH_MAILER_DISABLE"
if [ "$PUBLISH_MAILER_DISABLE" = true ]; then
    set_parameter "register_email_confirm" "false"
fi

if [ -n "$PUBLISH_PHP_MEMORY_LIMIT" ]; then
    echo ".htaccess: setting \"memory_limit\" to $PUBLISH_PHP_MEMORY_LIMIT"
    sed -i -E "s/php_value memory_limit .+/php_value memory_limit $PUBLISH_PHP_MEMORY_LIMIT/" web/.htaccess
fi

# at first run, create default super admin user if needed
SUPERADMIN_EXISTS=$(php app/console doctrine:mongodb:query Plantnet\\UserBundle\\Document\\User  '{"roles": ["ROLE_SUPER_ADMIN"]}')
if [ -z "$SUPERADMIN_EXISTS" ]; then
    if [ -z "$PUBLISH_ADMIN_USER" ] || [ -z "$PUBLISH_ADMIN_USER" ] || [ -z "$PUBLISH_ADMIN_PASSWORD" ]; then
        echo "cannot create ROLE_SUPER_ADMIN user; missing env. variables: \$PUBLISH_ADMIN_USER, \$PUBLISH_ADMIN_EMAIL, \$PUBLISH_ADMIN_PASSWORD"
        exit 1
    else
        echo "creating ROLE_SUPER_ADMIN user \"$PUBLISH_ADMIN_USER\" ($PUBLISH_ADMIN_EMAIL)"
        php app/console fos:user:create "$PUBLISH_ADMIN_USER" "$PUBLISH_ADMIN_EMAIL" "$PUBLISH_ADMIN_PASSWORD" && php app/console fos:user:promote "$PUBLISH_ADMIN_USER" ROLE_SUPER_ADMIN
    fi
else
    echo "user ROLE_SUPER_ADMIN already exists"
fi

# @TODO at first run, create Database collection ?

