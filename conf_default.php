<?php

$conf['phantomjs_bin'] = "/opt/phantomjs/bin/phantomjs";
$conf['phantomjs_exec'] = $conf['phantomjs_bin'] . " " . __DIR__ . "/netsniff/netsniff.js";

$conf['debug'] = 0;

# Should ping/mtr be enabled
$conf['pingmtr_enabled'] = true;
$conf['ping_bin'] = "/bin/ping";
$conf['mtr_bin'] = "/usr/bin/mtr";
$conf['nmap_bin'] = "/usr/bin/nmap";

# Read README on what are remotes
#$conf['remotes'][] = array("name" => "US", "provider" => "http_get", "base_url" => "http://myurl.usa/fantomtest/");
#$conf['remotes'][] = array("name" => "Europe", "provider" => "http_get", "base_url" => "http://myurl.eu/fantomtest/");

?>
