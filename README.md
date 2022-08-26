Pl@ntNet-Publish
================

Copyright (c) 2013-2014 CIRAD-INRA-INRIA-IRD

Based on Symphony 2

Pl@ntNet-Publish is distributed under a Cecill-V2 license (see [LICENSE](LICENSE))

Author : Antoine Affouard & Julien Barbe

## Deployment using Docker image (easier)

### install docker and docker-compose
Example for Ubuntu 22.04: https://dev.to/klo2k/install-docker-docker-compose-in-ubuntu-2204-in-5-commands-35m8

### build local publish image
```
sudo ./docker-build
```

### deploy and start service using docker-compose
Edit `docker-compose.yml` to your needs:
 * make sure `MONGO_INITDB_ROOT_*`, `ME_CONFIG_MONGODB_*` and `PUBLISH_MONGO_*` variables are set according to each other: same username, same password (don't forget `ME_CONFIG_MONGODB_URL`)
 * specify `PUBLISH_ADMIN_*` variables: this will set up a super-admin user to manage your Publish app
 * adjust `mongo/volume` for DB data persistence on the host filesystem (defaults to `/opt/plantnet-publish/db`)

Start service
```
sudo docker-compose up
```

Publish is running on http://localhost:8061

mongo-express is running on http://localhost:8062

## Installation on Ubuntu 20.04

see [wiki: installation on Ubuntu 20.04](https://github.com/plantnet/plantnet-publish/wiki/install_on_ubuntu_20_04)

## Installation on Ubuntu 14.04

see [README.14.04](README.14.04.md)
