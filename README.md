Pl@ntNet-Publish
================

Copyrigth (c) 2013-2014 CIRAD-INRA-INRIA-IRD

Based on Symphony 2

Pl@ntNet-Publish is under a Cecill-V2 license (see LICENSE)

Author : Antoine Affouard & Julien Barbe


Installation on Ubuntu 14.04
----------------------------

h3. Apache

<pre>
sudo apt-get install apache2 libapache2-mod-php5
</pre>


Requirements:
<pre>
sudo a2enmod rewrite
</pre>

h3. Apache config

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

h3. NodeJS / less

<pre>
> apt-get install nodejs npm node-less
</pre>


h3. Other dependencies

<pre>
sudo apt-get install openjdk-7-jre
</pre>


h3. Git

<pre>
> apt-get install git
</pre>

h3. PHP (v5.3.x)


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


h3. php.ini (/etc/php5/apache2/php.ini)

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

h3. MongoDb (v2.4.3)

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

h3. Get Composer (Dependency Manager for PHP)

https://getcomposer.org/download/
Put the composer.phar file where the project directory will be created

<pre>
cd /var/www/plantnet-publish
sudo php -r "readfile('https://getcomposer.org/installer');" | sudo php
</pre>

h3. Get copy

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

h3. User rights

<pre>
chown -R www-data:www-data ./
chmod 755 -R ./
</pre>


h3. Create first user (super admin)

<pre>
cd {project_path}
./app/console fos:user:create
./app/console fos:user:promote {first_user} ROLE_SUPER_ADMIN
</pre>

h3. Mondo DB Admin

use Genghis : http://genghisapp.com/

Download zip and unzip it in /var/www/html (or other apache directory)
and go to http://localhost/genghis/genghis.php

h3. Mail config

Edit app/config/parameters.yml
<pre>
 mailer_host: smtp.cirad.fr
 mailer_user: null
 mailer_password: null
</pre>

If no mail serveur is available, add in parameters.yml

<pre>
    mailer_disable: true
    register_email_confirm: false
</pre>


./app/console cache:clear --env=prod
chown www-data:www-data ./ -R

Common problems
===============
