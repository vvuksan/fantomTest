<?php

$conf['phantomjs_exec'] = "/opt/phantomjs/bin/phantomjs /var/www/fantomtest/phantomjs/netsniff.js";

# What XVFB display number is being used
$conf['display'] = ":1";

$conf['debug'] = 1;

$conf['pingmtr_enabled'] = true;
$conf['ping_bin'] = "/bin/ping";
$conf['mtr_bin'] = "/usr/bin/mtr";
$conf['nmap_bin'] = "/usr/bin/nmap";

# Read README on what are remotes
#$conf['remotes'][] = array("name" => "US", "provider" => "http_get", "base_url" => "http://myurl.usa/fantomtest/");
#$conf['remotes'][] = array("name" => "Europe", "provider" => "http_get", "base_url" => "http://myurl.eu/fantomtest/");

?>
