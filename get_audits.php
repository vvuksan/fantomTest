<?php

//////////////////////////////////////////////////////////////////////////////
// Ask har-poon to run Lighthouse audits for a URL and render the result.
// Called asynchronously from the waterfall tab after the HAR has rendered.
//////////////////////////////////////////////////////////////////////////////

header("Cache-Control: private, s-maxage=2");

require_once("./tools.php");
require_once("./tools_lighthouse.php");

function lh_error($message) {
  print '<div id="audits" class="lh-report lh-error"><span class="stat-label">Lighthouse lab metrics</span><div>' . htmlentities($message) . '</div></div>';
  exit;
}

$url = isset($_REQUEST['url']) ? validate_url(trim($_REQUEST['url'])) : FALSE;
if ( $url === FALSE ) {
  lh_error("No valid URL supplied");
}

$site_id = isset($_REQUEST['site_id']) && is_numeric($_REQUEST['site_id']) ? intval($_REQUEST['site_id']) : -1;

# Run on a remote node: the remote renders the HTML for us
if ( $site_id != -1 ) {
  if ( !isset($conf['remotes'][$site_id]['base_url']) ) {
    lh_error("Unknown remote site");
  }
  $base_url = rtrim($conf['remotes'][$site_id]['base_url'], '/');
  $ctx = stream_context_create(array("http" => array("timeout" => 180), "ssl" => array("verify_peer" => $conf['ssl_peer_verify'], "verify_peer_name" => $conf['ssl_peer_verify'])));
  $html = @file_get_contents($base_url . "/get_audits.php?site_id=-1&url=" . urlencode($url), false, $ctx);
  if ( $html === FALSE ) {
    lh_error("Remote " . $conf['remotes'][$site_id]['name'] . " did not return audits. It may be running an older version.");
  }
  print $html;
  exit;
}

if ( !isset($conf['harpoon_server_url']) ) {
  lh_error("Lighthouse audits need har-poon. Set \$conf['harpoon_server_url'] in conf.php.");
}

$audits = isset($conf['lighthouse_audits']) && is_array($conf['lighthouse_audits']) ? array_values($conf['lighthouse_audits']) : array("first-contentful-paint", "largest-contentful-paint");

$opts = array(
  'http' => array(
    'method'  => "POST",
    'header'  => "Content-Type: application/json\r\n",
    'content' => json_encode(array("audits" => $audits)),
    'timeout' => 180,
  )
);
$ctx = stream_context_create($opts);
$json = @file_get_contents($conf['harpoon_server_url'] . "?url=" . urlencode($url), false, $ctx);

if ( $json === FALSE ) {
  lh_error("har-poon did not respond at " . $conf['harpoon_server_url']);
}

$results = json_decode($json, TRUE);
if ( !isset($results['report']['audits']) ) {
  $msg = isset($results['error']) ? $results['error'] : "har-poon returned no Lighthouse report. Check that it is a version that supports audits.";
  lh_error($msg);
}

print render_lighthouse_report($results['report']);
