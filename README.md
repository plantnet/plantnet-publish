Pl@ntNet-Publish
================

Copyright (c) 2013-2014 CIRAD-INRA-INRIA-IRD

Based on Symphony 2

Pl@ntNet-Publish is distributed under a Cecill-V2 license (see [LICENSE](LICENSE))

Author : Antoine Affouard & Julien Barbe

# Deployment using Docker image (easier)

## install docker and docker-compose
Example for Ubuntu 22.04: https://dev.to/klo2k/install-docker-docker-compose-in-ubuntu-2204-in-5-commands-35m8

## build local publish image
```
sudo ./docker-build
```

## deploy and start service using docker-compose
Edit `docker-compose.yml` to your needs:
 * `PUBLISH_ADMIN_*`: at first run, sets up a super-admin user to manage your Publish app
 * `PUBLISH_MAILER_*`: if you want Publish to send you emails
 * `mongo/volume`: for DB data persistence on the host filesystem (defaults to `/opt/plantnet-publish/db`)
 * `ME_CONFIG_BASICAUTH_*`: for BasicAuth protection of Mongo Express GUI

Start service
```
sudo docker-compose up -d
```

Publish is running on http://localhost:8061

mongo-express is running on http://localhost:8062

## migrate data from publish.plantnet-project.org

### ask us for a data dump

We'll be happy to send you a dump of your data, containing database files in .bson format and media files in .zip format.

### restore mongodb databases

Install `bsondump` utility:
```
sudo apt-get install mongo-tools
```

Restore databases using [docker-restore-mongodb2-dumps.sh](https://github.com/plantnet/plantnet-publish/blob/master/docker-restore-mongodb2-dumps.sh)

Example for a project named "publish_prod_myproject":
```
./docker-restore-mongodb2-dumps.sh /path/to/database/dump/folder/
```

Repeat operation for all databases (usually `publish_prod` plus your project db).

### restore media files

Unzip media dump archive. You shoud find 2 folders `banners` and `uploads`. Copy them into the Docker container, then adjust permissions:
```
sudo docker cp banners plantnet-publish_publish_1:/var/www/plantnet-publish/web/
sudo docker cp uploads plantnet-publish_publish_1:/var/www/plantnet-publish/web/
sudo docker exec plantnet-publish_publish_1 /bin/chown www-data:www-data -R /var/www/plantnet-publish/web/uploads
sudo docker exec plantnet-publish_publish_1 /bin/chown www-data:www-data -R /var/www/plantnet-publish/web/banners
```

## update config

Edit `PUBLISH_MONGO_DATABASE` if needed in `docker-compose.yml`

# Installation on Ubuntu 20.04

see [wiki: installation on Ubuntu 20.04](https://github.com/plantnet/plantnet-publish/wiki/install_on_ubuntu_20_04)

# Installation on Ubuntu 14.04

see [README.14.04](README.14.04.md)
