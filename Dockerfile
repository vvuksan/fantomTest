FROM php:apache

RUN echo 'debconf debconf/frontend select Noninteractive' | debconf-set-selections
RUN DEBIAN_FRONTEND=noninteractive apt-get update && apt-get install -y mtr-tiny nmap libfontconfig

# Debian throws out this error DSO support routines:DLFCN_LOAD:could not load the shared library:dso_dlfcn.c:185:filename(libssl_conf.so):
RUN mv /etc/ssl/openssl.cnf /etc/ssl/openssl.cnf.bkp
RUN touch /etc/ssl/openssl.cnf

RUN mv /usr/local/etc/php/php.ini-production /usr/local/etc/php/php.ini
RUN sed -i 's;upload_max_filesize = .*;upload_max_filesize = 96M;g' /usr/local/etc/php/php.ini


RUN mkdir /var/www/html/fantomtest
COPY *.php /var/www/html/fantomtest/
ADD css /var/www/html/fantomtest/css
ADD img /var/www/html/fantomtest/img
RUN sed -i 's;CustomLog .*;CustomLog /dev/stdout combined;g' /etc/apache2/sites-enabled/000-default.conf
RUN sed -i 's;ErrorLog .*;ErrorLog /dev/stderr;g' /etc/apache2/sites-enabled/000-default.conf
CMD /usr/sbin/apache2ctl -D FOREGROUND
EXPOSE 80
