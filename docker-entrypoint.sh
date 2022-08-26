#!/bin/bash

set -e

echo "This is plantnet-publish entrypoint Docker script!"

# run most commands as www-data to prevent permissions/ownership mess
su www-data -s /bin/bash -c ./docker-startup.sh

# empty cache
rm -rf app/cache/*

exec "$@"
