#!/bin/bash

echo "This is plantnet-publish entrypoint Docker script!"

# run most commands as www-data to prevent permissions/ownership mess
su www-data -s /bin/bash -c ./docker-startup.sh

exec "$@"
