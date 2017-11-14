#!/usr/bin/env bash

#== Import script args ==

timezone=$(echo "$1")

#== Bash helpers ==

function info {
  echo " "
  echo "--> $1"
  echo " "
}

#== Provision script ==

info "Provision-script user: `whoami`"

info "Configure timezone"
timedatectl set-timezone ${timezone} --no-ask-password

info "Update OS software"
yum upgrade -y

info "Adding additional repos"
ln -s /app/vagrant/etc/yum.repos.d/nginx.repo /etc/yum.repos.d/nginx.repo
cd /tmp
curl 'https://setup.ius.io/' -o setup-ius.sh
bash setup-ius.sh
echo "Done!"

info "Install additional software"
yum install -y git mc wget epel-release
yum install -y php71u-bcmath php71u-cli php71u-common php71u-devel php71u-fpm php71u-gd php71u-imap php71u-intl php71u-mbstring php71u-mcrypt php71u-mysqlnd php71u-pdo php71u-pecl-geoip php71u-pecl-imagick php71u-pecl-memcached php71u-pecl-redis php71u-pecl-xdebug php71u-soap php71u-xml unzip nginx mariadb mariadb-devel mariadb-server memcached memcached-devel redis

info "Configure PHP-FPM"
sed -i 's/user = php-fpm/user = nginx/g' /etc/php-fpm.d/www.conf
sed -i 's/group = php-fpm/group = nginx/g' /etc/php-fpm.d/www.conf
cat << EOF > /etc/php.d/xdebug.ini
xdebug.remote_enable=1
xdebug.remote_connect_back=1
xdebug.remote_port=9000
xdebug.remote_autostart=1
EOF
echo "Done!"

info "Configure SELinux"
sed -i 's/SELINUX=enforcing/SELINUX=permissive/g' /etc/selinux/config
setenforce 0
usermod -a -G vagrant nginx
echo "Done!"

info "Enabling site configuration"
ln -s /app/vagrant/etc/nginx/conf.d/inmatefone3.backend.conf /etc/nginx/conf.d/inmatefone3.backend.conf
ln -s /app/vagrant/etc/nginx/conf.d/inmatefone3.conf /etc/nginx/conf.d/inmatefone3.conf
echo "Done!"

info "Enabling services"
systemctl enable nginx
systemctl start nginx
systemctl enable php-fpm
systemctl start php-fpm
systemctl enable mariadb
systemctl start mariadb
systemctl enable memcached
systemctl start memcached
systemctl enable redis
systemctl start redis
echo "Done!"

info "Initailize databases for MySQL"
mysql -uroot <<< "CREATE DATABASE inmatefone"
mysql -uroot <<< "CREATE DATABASE inmatefone_test"
mysql -uroot <<< "CREATE USER 'root'@'%' IDENTIFIED BY ''"
mysql -uroot <<< "GRANT ALL PRIVILEGES ON *.* TO 'root'@'%'"
mysql -uroot <<< "DROP USER 'root'@'localhost'"
mysql -uroot <<< "FLUSH PRIVILEGES"
echo "Done!"

info "Install composer"
curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/bin --filename=composer