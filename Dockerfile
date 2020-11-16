FROM php:apache

RUN echo 'debconf debconf/frontend select Noninteractive' | debconf-set-selections
RUN DEBIAN_FRONTEND=noninteractive apt-get update && apt-get install -y mtr-tiny nmap libfontconfig

RUN mkdir -p /opt/phantomjs/bin
ADD phantomjs.bin /opt/phantomjs/bin/phantomjs

# Debian throws out this error DSO support routines:DLFCN_LOAD:could not load the shared library:dso_dlfcn.c:185:filename(libssl_conf.so):
RUN mv /etc/ssl/openssl.cnf /etc/ssl/openssl.cnf.bkp
RUN touch /etc/ssl/openssl.cnf


RUN mkdir /var/www/html/fantomtest
COPY *.php /var/www/html/fantomtest/
ADD css /var/www/html/fantomtest/css
ADD img /var/www/html/fantomtest/img
ADD netsniff /var/www/html/fantomtest/netsniff
CMD /usr/sbin/apache2ctl -D FOREGROUND
EXPOSE 80
