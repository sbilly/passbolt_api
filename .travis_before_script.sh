#!/bin/sh

# Download composer
wget http://getcomposer.org/composer.phar
php composer.phar install --dev --no-interaction

# make app tmp writable
chmod -R 777 ./app/tmp

# Create databases
if [ '$DB' = 'mysql' ]; then
    mysql -e 'CREATE DATABASE passbolt';
    mysql -e 'CREATE DATABASE passbolt_test;';
fi

# If code sniffer, configure phpcs to use cakephp rules
if [ '$PHPCS' = '1' ]; then
    composer global require 'cakephp/cakephp-codesniffer:1.*';
    ~/.composer/vendor/bin/phpcs --config-set installed_paths ~/.composer/vendor/cakephp/cakephp-codesniffer;
fi

# Install php gnupg
echo yes | pecl install gnupg
echo "extension = memcached.so" >> ~/.phpenv/versions/$(phpenv version-name)/etc/php.ini

# Install acpu
if [[ ${TRAVIS_PHP_VERSION} == "7" -o ${TRAVIS_PHP_VERSION} == "7.1" ]] ; then
    print "yes" | pecl install apcu-5.1.3;
else
    print "yes" | pecl install apcu-4.0.11;
fi
echo -e "extension = apcu.so\napc.enable_cli=1" >> ~/.phpenv/versions/$(phpenv version-name)/etc/php.ini

# Configure cakephp session
# Cache strategy doesn't work on travis with PhP7, use default cake strategy.
if [[ ${TRAVIS_PHP_VERSION} == "7" -o ${TRAVIS_PHP_VERSION} == "7.1" ]] ; then
    sed -i "s/'defaults' => 'cache',/'defaults' => 'cake',/" ./app/Config/core.php;
fi

phpenv rehash
set +H

# Configure passbolt
echo "<?php
class DATABASE_CONFIG {
  public \$default = array(
    'datasource' => 'Database/Mysql',
    'database' => 'passbolt',
    'host' => '127.0.0.1',
    'login' => 'root',
    'password' => '',
    'persistent' => false,
  );
  public \$test = array(
    'datasource' => 'Database/Mysql',
    'database' => 'passbolt_test',
    'host' => '127.0.0.1',
    'login' => 'root',
    'password' => '',
    'persistent' => false,
  );
}" > app/Config/database.php
cp app/Config/core.php.default app/Config/core.php
cp app/Config/email.php.default app/Config/email.php
sed -i "s/('debug',\s0)/('debug', 2)/" ./app/Config/core.php
sed -i "s/DYhG93b0qyJfIxfs2guVoUubWwvniR2G0FgaC9mi/DYhG93b0qyJfIxfs2guVoUubWwvniR2G0FgbC9mi/" ./app/Config/core.php
sed -i "s/76859309657453542496749683645/76859309357453542496749683645/" ./app/Config/core.php
sed -i "s/\/\/Configure::write('App.fullBaseUrl',\s'http:\/\/example.com');/Configure::write('App.fullBaseUrl', 'http:\/\/127.0.0.1');/" ./app/Config/core.php
sed -i "s/\/\/date_default_timezone_set('UTC');/date_default_timezone_set('UTC');/" ./app/Config/core.php
echo "<?php
\$config = array(
  'App' => array(
    'ssl' => array(
      'force' => false,
    ),
    'registration' => array(
      'public' => true,
    ),
    'selenium' => array(
      'active' => true,
    ),
  ),
  'GPG' => array(
    'env' => array(
      'setenv' => true,
      'home'   => '/home/travis/.gnupg',
    ),
  ),
);" > app/Config/app.php