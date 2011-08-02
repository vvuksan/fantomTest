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

1. Download and install PhantomJS from http://www.phantomjs.org/. Install it
 in e.g. /opt/phantomjs. Make sure you include the examples files.
2. Make sure you have Xvfb installed ie.
   Debian/Ubuntu: apt-get install xvfb
   or
  Centos/RHEL: yum install xorg-x11-server-Xvfb
3. Install PHP scripts somewhere in the Web Server HTDOCS area. 
4. Configure conf.php. You will need to modify
  $conf['phantomjs_exec']
to point to where your phantomjs and netsniff.js are. 
5. Start up Xvfb e.g.
   Xvfb :1 -screen 1 1600x1200x16 &



License
=======
Apache
