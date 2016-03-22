<?php

$conf['phantomjs_bin'] = "/opt/phantomjs/bin/phantomjs";

if ( preg_match("/^2/", exec($conf['phantomjs_bin'] . " -v")) ) {
  $conf['phantomjs_exec'] = $conf['phantomjs_bin'] . " " . __DIR__ . "/netsniff/netsniff-v2.js";
} else {
  $conf['phantomjs_exec'] = $conf['phantomjs_bin'] . " " . __DIR__ . "/netsniff/netsniff.js";
}

$conf['debug'] = 0;

# IMPORTANT 
# Do not verify SSL peers. I am setting this to false since for most of my remote
# nodes I use self-signed certificates. This does expose me to MITM (man in the middle attack)
# however I am willing to accept that risk. Set this to true if you want to make verify
# peer certs
$conf['ssl_peer_verify'] = false;

# Try to detect CDNs used
$conf['cdn_detection'] = true;

# These are the headers that can be used in the URL test. If you are finding you are using the
# the same headers all the time you can set them here to default to a value
# For example Accept-Language:es || User-Agent:Mozilla
$conf['arbitrary_headers'] = "";

# Should ping/mtr be enabled
$conf['pingmtr_enabled'] = true;
$conf['ping_bin'] = "/bin/ping";
$conf['ping6_bin'] = "/bin/ping6";
$conf['mtr_bin'] = "/usr/bin/mtr";
$conf['nmap_bin'] = "/usr/bin/nmap";

# Read README on what are remotes
#$conf['remotes'][] = array("name" => "US", "provider" => "http_get", "base_url" => "http://myurl.usa/fantomtest/");
#$conf['remotes'][] = array("name" => "Europe", "provider" => "http_get", "base_url" => "http://myurl.eu/fantomtest/");

?>
