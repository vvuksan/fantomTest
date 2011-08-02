fantomTest
==========

This is a simple webapp that allows you to obtain HTTP performance of a web 
page by using getting the timings for all web page resources and plotting them
in a waterfall chart. In addition screenshot of the page will be produced.

To get timings we are utilizing the excellent PhantomJS semi-headless webKit
browser. PhantomJS will render a URL with all it's resources and produce HAR
(HTTP Archive) which is parsed to plot the waterfall chart.

Installation
============

* Download and install PhantomJS from http://www.phantomjs.org/. Install it
 in e.g. /opt/phantomjs. 
* Make sure you have Xvfb installed ie.

   Debian/Ubuntu: apt-get install xvfb

   or

  Centos/RHEL: yum install xorg-x11-server-Xvfb

* Install PHP scripts somewhere in the Web Server HTDOCS area. 
* Configure conf.php. This is the full path name to the phantomjs executable
and the netsniff.js which is distributed with fantomTest. netsniff.js is in the
fantomTest directory. For example if you install fantomTest in /var/www/html/fantomTest
and phantomjs binary is in /opt/phantomjs/phantomjs you would put following value

  $conf['phantomjs_exec'] = "/opt/phantomjs/phantomjs /var/www/html/fantomTest/phantomjs/netsniff.js";

* Start up Xvfb as the user running the webserver e.g.

   sudo -u apache Xvfb :1 -screen 1 1600x1200x16 &

* Now open up fantomTest in your browser.


License
=======
Apache
