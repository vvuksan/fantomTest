<?php

$conf['debug'] = 0;

# IMPORTANT 
# Do not verify SSL peers. I am setting this to false since for most of my remote
# nodes I use self-signed certificates. This does expose me to MITM (man in the middle attack)
# however I am willing to accept that risk. Set this to true if you want to make verify
# peer certs
$conf['ssl_peer_verify'] = false;

# Try to detect CDNs used in URL Test
$conf['cdn_detection'] = false;

# Show timing for requests in URL Test
$conf['show_url_timing_bar'] = false;

# Allow proxying requests in URL check
$conf['allow_proxy_for_url_check'] = false;

# Set CORS headers. If not set they are not set
#$conf["cors_headers_acao"] = "access-control-allow-origin: *";

# Prerender server https://github.com/prerender/prerender
# If you want to use HARrr server instead of PhantomJS uncomment below. Overrides any PhantomJS settings
#$conf['prerender_server_url'] = "http://<full_path>/har";

# For IP to AS resolution use local file-based cache. If following defined use the file as the cache file
#$conf['cache_file'] = "/var/www/cache/cache.json";
# Cache time
#$conf['cache_time'] = 8640000;

if ( getenv('HARPOON_URL') !== false ) {
  $conf['harpoon_server_url'] = getenv('HARPOON_URL');
}

# These are the headers that can be used in the URL test. If you are finding you are using the
# the same headers all the time you can set them here to default to a value
# For example Accept-Language:es || User-Agent:Mozilla
$conf['arbitrary_headers'] = "";

$conf['allowed_dns_query_types'] = array (
    "A",
    "AAAA",
    "CNAME",
    "MX",
    "SOA",
    "TXT",
    "NS",
    "CAA"
);

$conf['allowed_http_methods'] = array(
  "GET",
  "POST",
  "HEAD",
  "DELETE",
  "OPTIONS",
  "PURGE",
  "PATCH",
  "PUT"
);

# Should ping/mtr be enabled. Make sure paths to ping and mtr are correct. Otherwise 
# the tab will be disabled
$conf['pingmtr_enabled'] = true;
$conf['ping_bin'] = "/bin/ping";
$conf['ping6_bin'] = "/bin/ping6";
$conf['mtr_bin'] = "/usr/bin/mtr";
# 
if ( !( is_executable($conf['mtr_bin']) && ! is_executable($conf['ping_bin'] = "/bin/ping") ) ) {
  $conf['pingmtr_enabled'] = false;
}

# Should NMAP be available
$conf['nmap_bin'] = "/usr/bin/nmap";

$conf['jquery_js_path']    = "https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js";
$conf['jqueryui_js_path']  = "https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.14.1/jquery-ui.min.js";
$conf['jqueryui_css_path'] = "https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.14.1/themes/flick/jquery-ui.min.css";
$conf['jquery_tablesorter'] = "https://cdnjs.cloudflare.com/ajax/libs/jquery.tablesorter/2.25.7/js/jquery.tablesorter.min.js";

# Read README on what are remotes
#$conf['remotes'][] = array("name" => "US", "provider" => "http_get", "base_url" => "http://myurl.usa/fantomtest/");
#$conf['remotes'][] = array("name" => "Europe", "provider" => "http_get", "base_url" => "http://myurl.eu/fantomtest/");
