fantomTest
==========

FantomTest started as a simple webapp that allows you to obtain HTTP performance of a web 
page by getting the timings for all web page resources and plotting them in a waterfall chart.
This has been expanded to also include 

* individual URL test using CURL
* DNS resolution
* Ping/MTR results
* TLS certificates
* TLS Ciphers supported by the remote server

To get timings we are utilizing the excellent PhantomJS semi-headless webKit
browser. PhantomJS will render a URL with all it's resources and produce HAR
(HTTP Archive) which is parsed to plot the waterfall chart.

Installation
============

* Install PHP scripts somewhere in the Web Server HTDOCS area e.g. /var/www/fantomtest
* Configure conf.php. In most cases you will only need to configure the location of
phantomjs binary e.g.

  $conf['phantomjs_bin'] = "/opt/phantomjs/phantomjs";

* You can override any value in conf_default.php with the value in conf.php
* Now open up fantomTest in your browser.

Configuration
=============

If you install fantomtest on multiple nodes ie. say you have servers in Europe, USA you can access stats
from a single interface by configuring URLs in conf.php. Simply add following to your conf file. 

$conf['remotes'][] = array("name" => "US", "provider" => "http_get", "base_url" => "http://myurl.usa/fantomtest/");

to add additional ones simply repeat the line with the new name and URL.

License
=======
Apache
