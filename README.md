Pl@ntNet-Publish
================

Copyrigth (c) 2013-2014 CIRAD-INRA-INRIA-IRD

Based on Symphony 2

Pl@ntNet-Publish is under a Cecill-V2 license (see LICENSE)

Author : Antoine Affouard & Julien Barbe



Installation on Ubuntu 14.04
----------------------------

### Apache

<pre>
sudo apt-get install apache2 libapache2-mod-php5
</pre>


Requirements:
<pre>
sudo a2enmod rewrite
</pre>

### Apache config

Example
```
<VirtualHost *:80>
    ServerName publish.local
    ServerSignature Off

    DocumentRoot /var/www/plantnet-publish/plantnet-publishv2/web
    <Directory /var/www/plantnet-publish/plantnet-publishv2/web>
        Options -Indexes +FollowSymLinks +MultiViews
        php_admin_flag allow_url_fopen On
        AllowOverride All
        Order allow,deny
        Allow from all
    </Directory>
    ErrorLog /var/log/apache2/publish_error.log
    # Possible values include: debug, info, notice, warn, error, crit,
    # alert, emerg.
    LogLevel warn
</VirtualHost>
```

WARNING : if you use local name as above, please update your /etc/hosts file :
<pre>
127.0.0.1	localhost
127.0.0.1	publish.local
</pre>

### NodeJS / less

<pre>
> apt-get install nodejs npm node-less
</pre>


### Other dependencies

<pre>
sudo apt-get install openjdk-7-jre
</pre>


### Git

<pre>
> apt-get install git
</pre>

### PHP (v5.3.x)


Modules:
*apc*, Core, ctype, *curl*, date,dom, ereg, fileinfo, filter, *gd (with jpeg support)*, hash, iconv, *imagick*, *intl*, json, libxml, *mbstring*, *mcrypt*, *mhash*, *mongo*, *mysql*, mysqli *openssl*, *pcntl*, pcre, PDO, pdo_mysql, pdo_sqlite, Phar, posix, Reflection, session, *shmop*, SimpleXML, SPL, *SQLite*, sqlite3, standard, *sysvmsg*, *sysvsem*, *sysvshm*, *tidy*, tokenizer, xml, xmlreader, xmlwriter, *zip*, *zlib*


On Ubuntu 14.05

<pre>
sudo apt-get install php5-apcu php5-curl php5-gd php5-imagick php5-intl php5-mongo php5-mcrypt php5-mysql php5-sqlite php5-tidy php5-cli
</pre>


To check module use
<pre>
php -m
</pre>


### php.ini (/etc/php5/apache2/php.ini)

<pre>
php_flag magic_quotes_gpc off

memory_limit = 256M
register_globals = Off
post_max_size = 2000M
upload_max_filesize = 2000M
max_file_uploads = 20
allow_url_fopen = On
date.timezone = Europe/Paris

short_open_tag=Off
</pre>

### MongoDb (v2.4.3)

<pre>
sudo apt-get install mongodb mongodb-clients
</pre>

Database: bota
Collections: User, Database
<pre>
mongo
use bota
db.createCollection("User")
db.createCollection("Database")
quit()
</pre>

### Get Composer (Dependency Manager for PHP)

https://getcomposer.org/download/
Put the composer.phar file where the project directory will be created

<pre>
cd /var/www/plantnet-publish
sudo php -r "readfile('https://getcomposer.org/installer');" | sudo php
</pre>

### Get copy

Fetch URL : https://github.com/plantnet/plantnet-publish
<pre>
> git clone {Fetch_URL}
> cd plantnet-publishv2
> php ../composer.phar self-update
> php ../composer.phar install
> ./app/console assets:install --symlink
> ./app/console assetic:dump --env=prod
</pre>



If Node path error
<pre>
vi app/config/parameters.yml
</pre>
and ajust path

### User rights

<pre>
chown -R www-data:www-data ./
chmod 755 -R ./
</pre>


### Create first user (super admin)

<pre>
cd {project_path}
./app/console fos:user:create
./app/console fos:user:promote {first_user} ROLE_SUPER_ADMIN
</pre>

### Mondo DB Admin

use Genghis : http://genghisapp.com/

Download zip and unzip it in /var/www/html (or other apache directory)
and go to http://localhost/genghis/genghis.php

### Mail config

Edit app/config/parameters.yml
<pre>
 mailer_host: smtp.cirad.fr
 mailer_user: null
 mailer_password: null
</pre>

If no mail server is available, add in parameters.yml

<pre>
    mailer_disable: true
    register_email_confirm: false
</pre>


./app/console cache:clear --env=prod
chown www-data:www-data ./ -R

Common problems
===============


Included Librairies
-------------------
Bootstrap
Code and documentation copyright 2011-2015 Twitter, Inc. Code released under the MIT license. Docs released under Creative Commons.

CLEditor de Premium Software
You may use CLEditor under the terms of either the MIT License or the GNU General Public License (GPL) Version 2.

Fancybox
Copyright (c) 2012 Janis Skarnelis
Licensed under both MIT and GPL licenses

iviewer
Dmitry Petrov
Widget is licensed under the MIT (http://www.opensource.org/licenses/mit-license.php) license.

tablesorter
Christian Bach
Dual licensed (just pick!)under MIT or GPL licenses.

jQuery
Copyright 2015 The jQuery Foundation.
jQuery Foundation projects are released under the terms of the license specified in the project's repo or if not specified, under the MIT license.

jQuery UI
Copyright 2015 The jQuery Foundation.
jQuery Foundation projects are released under the terms of the license specified in the project's repo or if not specified, under the MIT license.

MouseWheel
Copyright (c) 2013 Brandon Aaron (http://brandon.aaron.sh)
Licensed under the MIT License (LICENSE.txt).

Spin.js
MIT License

Leaflet
© 2010–2014 Vladimir Agafonkin, 2010–2011 CloudMade. Maps © OpenStreetMap contributors.
https://github.com/Leaflet/Leaflet/blob/master/LICENSE

Leaflet.draw
(c) 2012-2013, Jacob Toye, Smartrak
MIT

Leaflet FullScreen
Copyright (c) 2013, Bruno Bergot
Released under the MIT License http://opensource.org/licenses/mit-license.php

Leaflet MarkerCluster
Copyright (c) 2012, Smartrak, David Leaver
Leaflet.markercluster is an open-source JavaScript library for Marker Clustering on leaflet powered maps.
MIT

Leaflet minimap
Copyright (c) 2012, Norkart AS
https://github.com/Norkart/Leaflet-MiniMap/blob/master/LICENSE.txt
