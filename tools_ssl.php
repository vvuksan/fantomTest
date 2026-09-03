<?php

#################################################################################################
# Load the local CA store so that we can tell whether a chain ends in a trusted root.
# Keyed by subject CN.
#################################################################################################
function get_ca_certs($ca_certs_file = "/etc/ssl/certs/ca-certificates.crt") {

  $ca_certs = array();

  if ( !is_readable($ca_certs_file) )
    return $ca_certs;

  # Get individual certs
  $certs = explode("-----BEGIN CERTIFICATE-----", file_get_contents($ca_certs_file));

  foreach ( $certs as $cert ) {

    $parsed_cert = @openssl_x509_parse("-----BEGIN CERTIFICATE-----\n" . $cert);
    if ( !$parsed_cert )
      continue;

    $cn_value = isset($parsed_cert["subject"]["CN"]) ? $parsed_cert["subject"]["CN"] : "unknown subject cn";
    $issuer_value = isset($parsed_cert["issuer"]["CN"]) ? $parsed_cert["issuer"]["CN"] : "unknown issuer";

    if ( $cn_value == $issuer_value ) {
        $ca_certs[$cn_value] = array (
            "ca_cert" => 1,
            "name" => $parsed_cert["name"],
            "organization" => isset($parsed_cert["subject"]["O"]) ? $parsed_cert["subject"]["O"] : "",
            "valid_to" => $parsed_cert["validTo_time_t"],
            "key_identifier" => isset($parsed_cert["extensions"]["subjectKeyIdentifier"]) ? $parsed_cert["extensions"]["subjectKeyIdentifier"] : ""
        );
    }
  }

  return $ca_certs;

}

#################################################################################################
# Does a certificate name (possibly a wildcard) match the requested host name
#################################################################################################
function tls_name_matches($pattern, $name) {
  $pattern = strtolower(trim($pattern));
  $name = strtolower(trim($name));
  if ( $pattern == $name )
    return true;
  # Wildcards only cover a single left-most label
  if ( substr($pattern, 0, 2) == "*." ) {
    $suffix = substr($pattern, 1);
    $dot = strpos($name, ".");
    return $dot !== false && substr($name, $dot) == $suffix;
  }
  return false;
}

#################################################################################################
# Pull out the interesting bits of a parsed certificate into a flat, display friendly array
#################################################################################################
function tls_summarize_cert($cert_resource, $parsed) {

  $ext = isset($parsed["extensions"]) ? $parsed["extensions"] : array();

  $summary = array(
    "subject_cn"   => isset($parsed["subject"]["CN"]) ? $parsed["subject"]["CN"] : (isset($parsed["subject"]["O"]) ? $parsed["subject"]["O"] : "(no CN)"),
    "subject_o"    => isset($parsed["subject"]["O"]) ? $parsed["subject"]["O"] : "",
    "issuer_cn"    => isset($parsed["issuer"]["CN"]) ? $parsed["issuer"]["CN"] : "",
    "issuer_o"     => isset($parsed["issuer"]["O"]) ? $parsed["issuer"]["O"] : "",
    "issuer_c"     => isset($parsed["issuer"]["C"]) ? $parsed["issuer"]["C"] : "",
    "serial"       => isset($parsed["serialNumberHex"]) ? $parsed["serialNumberHex"] : "",
    "valid_from"   => $parsed["validFrom_time_t"],
    "valid_to"     => $parsed["validTo_time_t"],
    "signature"    => isset($parsed["signatureTypeSN"]) ? $parsed["signatureTypeSN"] : "",
    "is_ca"        => isset($ext["basicConstraints"]) && preg_match("/CA:TRUE/i", $ext["basicConstraints"]),
    "self_signed"  => isset($parsed["subject"], $parsed["issuer"]) && $parsed["subject"] == $parsed["issuer"],
    "san_dns"      => array(),
    "san_ip"       => array(),
    "ocsp"         => array(),
    "ca_issuers"   => array(),
    "crl"          => array(),
    "sct_count"    => 0,
    "must_staple"  => isset($ext["tlsfeature"]) && preg_match("/status_request/i", $ext["tlsfeature"]),
    "key_usage"    => isset($ext["keyUsage"]) ? $ext["keyUsage"] : "",
    "ext_key_usage"=> isset($ext["extendedKeyUsage"]) ? $ext["extendedKeyUsage"] : "",
    "policies"     => isset($ext["certificatePolicies"]) ? $ext["certificatePolicies"] : "",
    "key_type"     => "",
    "key_bits"     => 0,
    "key_curve"    => "",
    "fp_sha256"    => "",
    "fp_sha1"      => "",
    "pem"          => "",
  );

  if ( isset($ext["subjectAltName"]) ) {
    foreach ( explode(",", $ext["subjectAltName"]) as $san ) {
      $san = trim($san);
      if ( preg_match("/^DNS:(.*)$/i", $san, $out) )
        $summary["san_dns"][] = $out[1];
      else if ( preg_match("/^IP Address:(.*)$/i", $san, $out) )
        $summary["san_ip"][] = $out[1];
    }
  }

  if ( isset($ext["authorityInfoAccess"]) ) {
    if ( preg_match_all("/OCSP - URI:(\S+)/", $ext["authorityInfoAccess"], $out) )
      $summary["ocsp"] = $out[1];
    if ( preg_match_all("/CA Issuers - URI:(\S+)/", $ext["authorityInfoAccess"], $out) )
      $summary["ca_issuers"] = $out[1];
  }

  if ( isset($ext["crlDistributionPoints"]) && preg_match_all("/URI:(\S+)/", $ext["crlDistributionPoints"], $out) )
    $summary["crl"] = $out[1];

  if ( isset($ext["ct_precert_scts"]) )
    $summary["sct_count"] = preg_match_all("/Signed Certificate Timestamp/", $ext["ct_precert_scts"], $out);

  # Public key details
  $pkey = @openssl_pkey_get_public($cert_resource);
  if ( $pkey ) {
    $details = openssl_pkey_get_details($pkey);
    $summary["key_bits"] = isset($details["bits"]) ? $details["bits"] : 0;
    switch ( isset($details["type"]) ? $details["type"] : -1 ) {
      case OPENSSL_KEYTYPE_RSA: $summary["key_type"] = "RSA"; break;
      case OPENSSL_KEYTYPE_DSA: $summary["key_type"] = "DSA"; break;
      case OPENSSL_KEYTYPE_DH:  $summary["key_type"] = "DH"; break;
      case OPENSSL_KEYTYPE_EC:
        $summary["key_type"] = "EC";
        $summary["key_curve"] = isset($details["ec"]["curve_name"]) ? $details["ec"]["curve_name"] : "";
        break;
      default:
        # Ed25519/Ed448 and friends report no type; sniff the PEM header instead
        $summary["key_type"] = isset($details["key"]) && preg_match("/BEGIN PUBLIC KEY/", $details["key"]) ? "EdDSA" : "Unknown";
    }
  }

  $summary["fp_sha256"] = @openssl_x509_fingerprint($cert_resource, "sha256");
  $summary["fp_sha1"] = @openssl_x509_fingerprint($cert_resource, "sha1");
  @openssl_x509_export($cert_resource, $summary["pem"]);

  return $summary;
}

#################################################################################################
# Connect to the host, capture the chain and connection parameters, and work out whether the
# chain ends in a root we trust.
#################################################################################################
function check_certificate_chain($hostname, $port, $sni_hostname, $debug = 0) {

  # Get list of all root certificates on the local machine
  $ca_certs = get_ca_certs();

  $base_options = array(
      "capture_peer_cert_chain" => true,
      "SNI_enabled" => true,
  );
  if ( $sni_hostname != "" ) {
    $base_options["SNI_server_name"] = $sni_hostname;
    # Verify the certificate against the SNI name rather than the address we connected to
    $base_options["peer_name"] = $sni_hostname;
  }

  # First attempt: strict verification against the local CA store, exactly like a browser would
  $strict = $base_options + array(
      "allow_self_signed" => false,
      "verify_peer_name" => true,
      "verify_peer" => true,
  );
  $ctx = stream_context_create( array("ssl" => $strict) );
  error_clear_last();
  $fp = @stream_socket_client("ssl://$hostname:$port", $errno, $errstr, 6, STREAM_CLIENT_CONNECT, $ctx);
  $verified = $fp ? true : false;
  $verify_error = "";
  if ( !$fp ) {
    # PHP reports handshake failures as warnings rather than through $errstr, so dig the OpenSSL reason out of the last error
    $last = error_get_last();
    if ( $last && preg_match("/OpenSSL Error messages:\s*(.*)$/s", $last["message"], $out) ) {
      $verify_error = trim(preg_replace("/error:[0-9A-F]+:SSL routines::?/", "", $out[1]));
    } else if ( $last && preg_match("/stream_socket_client\(\): (.*)$/s", $last["message"], $out) ) {
      $verify_error = trim($out[1]);
    } else {
      $verify_error = $errstr;
    }
  }

  # If strict verification failed, reconnect with verification off so we can still inspect the chain
  if ( !$fp ) {
    $loose = $base_options + array(
      "allow_self_signed" => true,
      "verify_peer_name" => false,
      "verify_peer" => false
    );
    $ctx = stream_context_create( array("ssl" => $loose) );
    error_clear_last();
    $fp = @stream_socket_client("ssl://{$hostname}:{$port}", $errno, $errstr, 6, STREAM_CLIENT_CONNECT, $ctx);
  }

  if ( !$fp ) {
    $reason = $errstr;
    $last = error_get_last();
    if ( $reason == "" && $last && preg_match("/stream_socket_client\(\): (.*)$/s", $last["message"], $out) ) {
      $reason = trim(preg_replace("/error:[0-9A-F]+:SSL routines::?/", "", $out[1]));
      if ( preg_match("/wrong version number|unknown protocol|packet length too long/i", $reason) )
        $reason = "The server did not speak TLS on this port. " . $reason;
    }
    return array("connected" => false, "success" => 0, "message" => $reason != "" ? $reason : "Connection failed or timed out", "certs" => array());
  }

  $meta = stream_get_meta_data($fp);
  $crypto = isset($meta["crypto"]) ? $meta["crypto"] : array();
  $params = stream_context_get_params($fp);
  fclose($fp);

  $chain = isset($params["options"]["ssl"]["peer_certificate_chain"]) ? $params["options"]["ssl"]["peer_certificate_chain"] : array();

  $captured_certs = array();
  $by_subject = array();
  foreach ( $chain as $position => $cert ) {
    $parsed = openssl_x509_parse($cert);
    if ( !$parsed )
      continue;
    $summary = tls_summarize_cert($cert, $parsed);
    $summary["position"] = $position;
    $summary["raw"] = $parsed;
    $captured_certs[] = $summary;
    $by_subject[$summary["subject_cn"]] = count($captured_certs) - 1;
  }

  if ( count($captured_certs) == 0 ) {
    return array("connected" => true, "success" => 0, "message" => "Server presented no certificate", "certs" => array(), "crypto" => $crypto);
  }

  # The leaf is the first non-CA certificate, falling back to the first one sent
  $leaf_index = 0;
  foreach ( $captured_certs as $i => $c ) {
    if ( !$c["is_ca"] ) { $leaf_index = $i; break; }
  }

  # Walk from the leaf up through the sent intermediates until we hit a root in the CA store
  $chain_trusted = false;
  $trusted_root = null;
  $missing_issuer = "";
  $issuer_cn = $captured_certs[$leaf_index]["issuer_cn"];
  $order = array();
  $seen = array();
  $order[] = $leaf_index;
  $seen[$leaf_index] = true;
  for ( $hops = 0; $hops < 8; $hops++ ) {
    if ( isset($ca_certs[$issuer_cn]) ) {
      $chain_trusted = true;
      $trusted_root = $issuer_cn;
      break;
    }
    if ( isset($by_subject[$issuer_cn]) && !isset($seen[$by_subject[$issuer_cn]]) ) {
      $idx = $by_subject[$issuer_cn];
      $order[] = $idx;
      $seen[$idx] = true;
      # A self-signed cert sent by the server that is not in our store ends the walk
      if ( $captured_certs[$idx]["self_signed"] ) {
        $missing_issuer = $issuer_cn;
        break;
      }
      $issuer_cn = $captured_certs[$idx]["issuer_cn"];
      continue;
    }
    $missing_issuer = $issuer_cn;
    break;
  }
  # Anything the server sent that is not part of the walked path
  foreach ( $captured_certs as $i => $c ) {
    if ( !isset($seen[$i]) ) $order[] = $i;
  }

  $root_sent = false;
  foreach ( $captured_certs as $c ) {
    if ( $c["self_signed"] ) $root_sent = true;
  }

  $leaf = $captured_certs[$leaf_index];
  if ( $leaf["valid_to"] < time() ) {
    $failure_message = "Certificate expired on " . date("Y-m-d", $leaf["valid_to"]);
  } else if ( $leaf["valid_from"] > time() ) {
    $failure_message = "Certificate is not valid until " . date("Y-m-d", $leaf["valid_from"]);
  } else if ( !$chain_trusted ) {
    $failure_message = "Issuer \"" . $missing_issuer . "\" was not sent by the server and is not in the local CA store";
  } else {
    $failure_message = $verify_error ? $verify_error : "Certificate does not match the requested host name";
  }

  return array(
    "connected"      => true,
    "verified"       => $verified,
    "verify_error"   => $verify_error,
    "crypto"         => $crypto,
    "certs"          => $captured_certs,
    "leaf_index"     => $leaf_index,
    "chain_order"    => $order,
    "chain_trusted"  => $chain_trusted,
    "trusted_root"   => $trusted_root,
    "trusted_root_info" => $trusted_root ? $ca_certs[$trusted_root] : null,
    "root_sent"      => $root_sent,
    "missing_issuer" => $missing_issuer,
    "success"        => $verified ? 1 : 0,
    "message"        => $verified ? "" : $failure_message
  );

} // end of function

#################################################################################################
# Maximum certificate lifetime browsers accept, based on when it was issued (CA/B Forum SC-081)
#################################################################################################
function tls_max_validity_days($issued_ts) {
  if ( $issued_ts < strtotime("2020-09-01") ) return 825;
  if ( $issued_ts < strtotime("2026-03-15") ) return 398;
  if ( $issued_ts < strtotime("2027-03-15") ) return 200;
  if ( $issued_ts < strtotime("2029-03-15") ) return 100;
  return 47;
}

#################################################################################################
# Turn the connection results into a list of pass/warn/fail checks
#################################################################################################
function analyze_certificate($results, $expected_name, $expected_is_ip) {

  $checks = array();
  $add = function($status, $label, $detail = "") use (&$checks) {
    $checks[] = array("status" => $status, "label" => $label, "detail" => $detail);
  };

  $leaf = $results["certs"][$results["leaf_index"]];
  $now = time();

  # Trust
  if ( $results["verified"] ) {
    $add("ok", "Chain of trust", "Verified against the local CA store, root: " . $results["trusted_root"]);
  } else if ( $results["chain_trusted"] ) {
    $add("ok", "Chain of trust", "Chain ends in trusted root " . $results["trusted_root"]);
  } else if ( $leaf["self_signed"] ) {
    $add("bad", "Chain of trust", "Certificate is self-signed");
  } else {
    # Was the untrusted issuer actually sent by the server? Then it is a private/unknown root rather than a gap in the chain.
    $sent_root = false;
    foreach ( $results["certs"] as $c ) if ( $c["subject_cn"] == $results["missing_issuer"] && $c["self_signed"] ) $sent_root = true;
    if ( $sent_root )
      $add("bad", "Chain of trust", "Root \"" . $results["missing_issuer"] . "\" is not in the local CA store");
    else
      $add("bad", "Chain of trust", "Issuer \"" . $results["missing_issuer"] . "\" not found. The server may not be sending its intermediate certificate.");
  }

  # Host name
  if ( $expected_name != "" ) {
    $matched = "";
    if ( $expected_is_ip ) {
      foreach ( $leaf["san_ip"] as $ip ) if ( $ip == $expected_name ) $matched = $ip;
    } else {
      foreach ( $leaf["san_dns"] as $san ) if ( tls_name_matches($san, $expected_name) ) $matched = $san;
      if ( $matched == "" && count($leaf["san_dns"]) == 0 && tls_name_matches($leaf["subject_cn"], $expected_name) )
        $matched = $leaf["subject_cn"] . " (CN only)";
    }
    if ( $matched != "" )
      $add("ok", "Host name", $expected_name . " matches " . $matched);
    else
      $add("bad", "Host name", $expected_name . " is not covered by this certificate");
  } else {
    $add("info", "Host name", "Connected by IP without an SNI name, so host name matching was skipped");
  }

  # Validity
  $days_left = floor(($leaf["valid_to"] - $now) / 86400);
  if ( $leaf["valid_to"] < $now )
    $add("bad", "Expiry", "Expired " . abs($days_left) . " days ago on " . date("Y-m-d", $leaf["valid_to"]));
  else if ( $leaf["valid_from"] > $now )
    $add("bad", "Expiry", "Not valid until " . date("Y-m-d H:i T", $leaf["valid_from"]));
  else if ( $days_left < 14 )
    $add("bad", "Expiry", "Expires in " . $days_left . " days on " . date("Y-m-d", $leaf["valid_to"]));
  else if ( $days_left < 30 )
    $add("warn", "Expiry", "Expires in " . $days_left . " days on " . date("Y-m-d", $leaf["valid_to"]));
  else
    $add("ok", "Expiry", "Valid for another " . $days_left . " days, until " . date("Y-m-d", $leaf["valid_to"]));

  $lifetime = round(($leaf["valid_to"] - $leaf["valid_from"]) / 86400);
  $max_days = tls_max_validity_days($leaf["valid_from"]);
  if ( $lifetime > $max_days )
    $add("warn", "Lifetime", $lifetime . " day validity exceeds the " . $max_days . " day maximum for certificates issued " . date("Y-m-d", $leaf["valid_from"]));
  else
    $add("ok", "Lifetime", $lifetime . " days, within the " . $max_days . " day maximum for its issue date");

  # Key
  $key_desc = $leaf["key_type"] . " " . ($leaf["key_curve"] ? $leaf["key_curve"] : $leaf["key_bits"] . " bit");
  if ( $leaf["key_type"] == "RSA" && $leaf["key_bits"] < 2048 )
    $add("bad", "Key strength", $key_desc . " is below the 2048 bit minimum");
  else if ( $leaf["key_type"] == "EC" && $leaf["key_bits"] < 256 )
    $add("bad", "Key strength", $key_desc . " is weak");
  else if ( $leaf["key_type"] == "DSA" )
    $add("warn", "Key strength", "DSA keys are deprecated");
  else
    $add("ok", "Key strength", $key_desc);

  # Signature
  if ( preg_match("/sha1|md5|md2/i", $leaf["signature"]) )
    $add("bad", "Signature", $leaf["signature"] . " is deprecated and rejected by browsers");
  else
    $add("ok", "Signature", $leaf["signature"]);

  # Names
  if ( count($leaf["san_dns"]) + count($leaf["san_ip"]) == 0 )
    $add("warn", "Subject Alternative Names", "None. Browsers ignore the CN, so this certificate will not match any host");
  else {
    $wild = 0;
    foreach ( $leaf["san_dns"] as $san ) if ( substr($san, 0, 2) == "*." ) $wild++;
    $add("ok", "Subject Alternative Names", count($leaf["san_dns"]) . " DNS" . (count($leaf["san_ip"]) ? ", " . count($leaf["san_ip"]) . " IP" : "") . ($wild ? ", " . $wild . " wildcard" : ""));
  }

  # Chain hygiene
  $intermediates = 0;
  foreach ( $results["certs"] as $c ) if ( $c["is_ca"] && !$c["self_signed"] ) $intermediates++;
  if ( $results["root_sent"] )
    $add("info", "Chain", count($results["certs"]) . " certificates sent, including the root. Sending the root is unnecessary and wastes bytes on every handshake");
  else if ( !$leaf["self_signed"] && $intermediates == 0 )
    $add("bad", "Chain", "Server sent only the leaf certificate, no intermediates");
  else
    $add("ok", "Chain", count($results["certs"]) . " certificates sent, " . $intermediates . " intermediate" . ($intermediates == 1 ? "" : "s"));
  if ( $results["leaf_index"] != 0 )
    $add("warn", "Chain order", "Leaf certificate was not sent first");

  # Revocation & transparency
  $revocation = array();
  if ( count($leaf["ocsp"]) ) $revocation[] = "OCSP";
  if ( count($leaf["crl"]) ) $revocation[] = "CRL";
  if ( count($revocation) )
    $add("ok", "Revocation", join(" and ", $revocation) . ($leaf["must_staple"] ? ", OCSP must-staple" : ""));
  else
    $add("warn", "Revocation", "No OCSP responder or CRL distribution point");

  if ( $leaf["sct_count"] >= 2 )
    $add("ok", "Certificate Transparency", $leaf["sct_count"] . " embedded SCTs");
  else if ( $leaf["sct_count"] == 1 )
    $add("warn", "Certificate Transparency", "Only 1 embedded SCT, browsers require at least 2");
  else if ( !$leaf["self_signed"] && $results["chain_trusted"] )
    $add("warn", "Certificate Transparency", "No embedded SCTs. Browsers require CT for publicly trusted certificates unless delivered via TLS extension or OCSP");

  # Protocol
  if ( isset($results["crypto"]["protocol"]) ) {
    $proto = $results["crypto"]["protocol"];
    $cipher = isset($results["crypto"]["cipher_name"]) ? $results["crypto"]["cipher_name"] : "";
    if ( preg_match("/TLSv1\.[23]/", $proto) )
      $add("ok", "Protocol", $proto . " negotiated with " . $cipher);
    else
      $add("bad", "Protocol", $proto . " negotiated. Anything below TLS 1.2 is obsolete");
  }

  return $checks;
}

#################################################################################################
# HTML helpers
#################################################################################################
function tls_row($label, $value_html) {
  return '<div class="tls-k">' . $label . '</div><div class="tls-v">' . $value_html . '</div>';
}

function tls_issuer_text($cert) {
  $parts = array();
  if ( $cert["issuer_o"] != "" ) $parts[] = $cert["issuer_o"];
  if ( $cert["issuer_cn"] != "" && $cert["issuer_cn"] != $cert["issuer_o"] ) $parts[] = $cert["issuer_cn"];
  return htmlentities(join(" · ", $parts));
}

function tls_key_text($cert) {
  if ( $cert["key_type"] == "EC" )
    return "EC " . ($cert["key_curve"] ? $cert["key_curve"] : $cert["key_bits"] . " bit");
  return trim($cert["key_type"] . " " . ($cert["key_bits"] ? $cert["key_bits"] . " bit" : ""));
}

function tls_role($cert, $results) {
  if ( $cert["position"] == $results["leaf_index"] ) return "Leaf";
  if ( $cert["self_signed"] ) return "Root";
  if ( $cert["is_ca"] ) return "Intermediate";
  return "Extra";
}

#################################################################################################
# Render the full report
#################################################################################################
function render_certificate_report($results, $checks, $target, $port, $sni_name) {

  $leaf = $results["certs"][$results["leaf_index"]];
  $now = time();
  $days_left = floor(($leaf["valid_to"] - $now) / 86400);

  $bad = 0; $warn = 0;
  foreach ( $checks as $c ) {
    if ( $c["status"] == "bad" ) $bad++;
    if ( $c["status"] == "warn" ) $warn++;
  }

  $expected = $sni_name != "" ? $sni_name : $target;
  $out = '<div class="tls-report">';

  # Verdict banner
  if ( $results["verified"] && $bad == 0 ) {
    $out .= '<div class="tls-verdict ok"><span class="tls-verdict-icon">&#10003;</span><div><b>Certificate is valid for ' . htmlentities($expected) . '</b>' .
      '<span>Trusted chain, ' . $days_left . ' days until expiry' . ($warn ? ', ' . $warn . ' warning' . ($warn > 1 ? 's' : '') . ' below' : '') . '</span></div></div>';
  } else if ( $results["verified"] ) {
    $out .= '<div class="tls-verdict warn"><span class="tls-verdict-icon">!</span><div><b>Certificate is trusted but has issues</b><span>' . $bad . ' failed check' . ($bad > 1 ? 's' : '') . ' below</span></div></div>';
  } else {
    # Lead with the concrete failing checks; fall back to whatever OpenSSL told us
    $reasons = array();
    foreach ( $checks as $c ) if ( $c["status"] == "bad" ) $reasons[] = $c["detail"];
    $reason = count($reasons) ? join(". ", $reasons) : $results["message"];
    if ( $reason == "" && $results["verify_error"] != "" ) $reason = "OpenSSL: " . $results["verify_error"];
    $out .= '<div class="tls-verdict bad"><span class="tls-verdict-icon">&#10005;</span><div><b>Certificate is not valid for ' . htmlentities($expected) . '</b><span>' . htmlentities($reason) . '</span></div></div>';
  }

  $out .= '<div class="tls-columns">';

  # --- Certificate facts ---
  $out .= '<section class="tls-card"><h3>Certificate</h3><div class="tls-kv">';
  $out .= tls_row("Common name", '<b>' . htmlentities($leaf["subject_cn"]) . '</b>' . ($leaf["subject_o"] ? ' <span class="tls-muted">' . htmlentities($leaf["subject_o"]) . '</span>' : ''));

  $san_html = '';
  foreach ( $leaf["san_dns"] as $san ) {
    $match = ($sni_name != "" || $target != "") && tls_name_matches($san, $expected);
    $san_html .= '<span class="tls-chip' . ($match ? ' match' : '') . '"' . ($match ? ' title="Matches the requested name"' : '') . '>' . htmlentities($san) . '</span>';
  }
  foreach ( $leaf["san_ip"] as $ip ) {
    $match = $ip == $expected;
    $san_html .= '<span class="tls-chip' . ($match ? ' match' : '') . '">' . htmlentities($ip) . '</span>';
  }
  $out .= tls_row("Alternative names", $san_html != '' ? '<div class="tls-chips">' . $san_html . '</div>' : '<span class="tls-muted">none</span>');
  $out .= tls_row("Issued by", tls_issuer_text($leaf) . ($leaf["issuer_c"] ? ' <span class="tls-muted">(' . htmlentities($leaf["issuer_c"]) . ')</span>' : ''));
  $out .= tls_row("Valid from", '<span class="mono">' . date("Y-m-d H:i T", $leaf["valid_from"]) . '</span>');

  if ( $leaf["valid_to"] < $now ) $exp_class = "bad";
  else if ( $days_left < 30 ) $exp_class = "warn";
  else $exp_class = "ok";
  $out .= tls_row("Valid until", '<span class="mono">' . date("Y-m-d H:i T", $leaf["valid_to"]) . '</span> <span class="tls-pill ' . $exp_class . '">' .
    ($days_left < 0 ? 'expired ' . abs($days_left) . ' days ago' : $days_left . ' days left') . '</span>');
  $out .= tls_row("Public key", htmlentities(tls_key_text($leaf)));
  $out .= tls_row("Signature", htmlentities($leaf["signature"]));
  $out .= tls_row("Serial", '<span class="mono tls-wrap">' . htmlentities($leaf["serial"]) . '</span>');
  $out .= tls_row("SHA-256", '<span class="mono tls-wrap">' . htmlentities(strtoupper(join(":", str_split($leaf["fp_sha256"], 2)))) . '</span>');
  $out .= tls_row("SHA-1", '<span class="mono tls-wrap">' . htmlentities(strtoupper(join(":", str_split($leaf["fp_sha1"], 2)))) . '</span>');

  $rev = array();
  foreach ( $leaf["ocsp"] as $u ) $rev[] = 'OCSP <a href="' . htmlentities($u) . '" target="_blank" rel="noopener">' . htmlentities($u) . '</a>';
  foreach ( $leaf["crl"] as $u ) $rev[] = 'CRL <a href="' . htmlentities($u) . '" target="_blank" rel="noopener">' . htmlentities($u) . '</a>';
  $out .= tls_row("Revocation", count($rev) ? join('<br>', $rev) : '<span class="tls-muted">none</span>');
  if ( count($leaf["ca_issuers"]) )
    $out .= tls_row("CA issuers", '<a href="' . htmlentities($leaf["ca_issuers"][0]) . '" target="_blank" rel="noopener">' . htmlentities($leaf["ca_issuers"][0]) . '</a>');
  $out .= tls_row("Transparency", $leaf["sct_count"] ? $leaf["sct_count"] . ' embedded SCTs' : '<span class="tls-muted">no embedded SCTs</span>');
  if ( $leaf["ext_key_usage"] )
    $out .= tls_row("Usage", htmlentities($leaf["ext_key_usage"]));
  $out .= '</div></section>';

  # --- Connection + checks ---
  $out .= '<div class="tls-side">';
  $out .= '<section class="tls-card"><h3>Connection</h3><div class="tls-kv">';
  $out .= tls_row("Endpoint", '<span class="mono">' . htmlentities($target) . ':' . intval($port) . '</span>');
  $out .= tls_row("SNI", $sni_name != "" ? '<span class="mono">' . htmlentities($sni_name) . '</span>' : '<span class="tls-muted">' . ($target != "" ? 'same as endpoint' : 'none') . '</span>');
  if ( isset($results["crypto"]["protocol"]) ) {
    $proto_ok = preg_match("/TLSv1\.[23]/", $results["crypto"]["protocol"]);
    $out .= tls_row("Protocol", '<span class="tls-pill ' . ($proto_ok ? 'ok' : 'bad') . '">' . htmlentities($results["crypto"]["protocol"]) . '</span>');
    $out .= tls_row("Cipher", '<span class="mono">' . htmlentities($results["crypto"]["cipher_name"]) . '</span> <span class="tls-muted">' . intval($results["crypto"]["cipher_bits"]) . ' bit</span>');
  }
  $out .= '</div></section>';

  $out .= '<section class="tls-card"><h3>Checks</h3><ul class="tls-checks">';
  foreach ( $checks as $c ) {
    $icon = array("ok" => "&#10003;", "warn" => "!", "bad" => "&#10005;", "info" => "i");
    $out .= '<li class="' . $c["status"] . '"><span class="tls-check-icon">' . $icon[$c["status"]] . '</span><div><b>' . htmlentities($c["label"]) . '</b>' .
      ($c["detail"] != "" ? '<span>' . htmlentities($c["detail"]) . '</span>' : '') . '</div></li>';
  }
  $out .= '</ul></section>';
  $out .= '</div>'; // tls-side
  $out .= '</div>'; // tls-columns

  # --- Chain ---
  $out .= '<section class="tls-card"><h3>Chain</h3><ol class="tls-chain">';
  foreach ( $results["chain_order"] as $idx ) {
    $c = $results["certs"][$idx];
    $role = tls_role($c, $results);
    $cls = strtolower($role);
    $expired = $c["valid_to"] < $now;
    $out .= '<li class="tls-node ' . $cls . '">' .
      '<div class="tls-node-head"><span class="tls-role ' . $cls . '">' . $role . '</span><b>' . htmlentities($c["subject_cn"]) . '</b>' .
      ($c["subject_o"] && $c["subject_o"] != $c["subject_cn"] ? ' <span class="tls-muted">' . htmlentities($c["subject_o"]) . '</span>' : '') . '</div>' .
      '<div class="tls-node-meta">' .
        '<span>Issued by <b>' . tls_issuer_text($c) . '</b></span>' .
        '<span class="' . ($expired ? 'tls-bad' : '') . '">Expires ' . date("Y-m-d", $c["valid_to"]) . ($expired ? ' (expired)' : '') . '</span>' .
        '<span>' . htmlentities(tls_key_text($c)) . ', ' . htmlentities($c["signature"]) . '</span>' .
        '<span class="mono" title="SHA-256 fingerprint">' . htmlentities(substr($c["fp_sha256"], 0, 16)) . '…</span>' .
      '</div></li>';
  }
  if ( $results["trusted_root"] && !$results["root_sent"] ) {
    $info = $results["trusted_root_info"];
    $out .= '<li class="tls-node root store"><div class="tls-node-head"><span class="tls-role root">Root</span><b>' . htmlentities($results["trusted_root"]) . '</b>' .
      ($info["organization"] ? ' <span class="tls-muted">' . htmlentities($info["organization"]) . '</span>' : '') . '</div>' .
      '<div class="tls-node-meta"><span>From the local CA store, not sent by the server</span><span>Expires ' . date("Y-m-d", $info["valid_to"]) . '</span></div></li>';
  } else if ( !$results["chain_trusted"] && !$leaf["self_signed"] ) {
    $out .= '<li class="tls-node missing"><div class="tls-node-head"><span class="tls-role missing">Missing</span><b>' . htmlentities($results["missing_issuer"]) . '</b></div>' .
      '<div class="tls-node-meta"><span>Not sent by the server and not in the local CA store</span></div></li>';
  }
  $out .= '</ol></section>';

  # --- Raw details, PEM, verification commands ---
  $out .= '<details class="tls-details"><summary>All certificate fields</summary>';
  foreach ( $results["chain_order"] as $idx ) {
    $c = $results["certs"][$idx];
    $out .= '<h4>' . tls_role($c, $results) . ': ' . htmlentities($c["subject_cn"]) . '</h4><div class="tls-kv raw">';
    foreach ( $c["raw"] as $key => $value ) {
      if ( $key == "purposes" ) continue;
      if ( preg_match("/_time_t$/", $key) ) $value = date("r", $value);
      if ( is_array($value) ) {
        $inner = '';
        foreach ( $value as $k => $v ) $inner .= '<div class="tls-k">' . htmlentities($k) . '</div><div class="tls-v"><pre>' . htmlentities(is_array($v) ? json_encode($v) : $v) . '</pre></div>';
        $out .= '<div class="tls-k">' . htmlentities($key) . '</div><div class="tls-v"><div class="tls-kv nested">' . $inner . '</div></div>';
      } else {
        $out .= '<div class="tls-k">' . htmlentities($key) . '</div><div class="tls-v"><pre>' . htmlentities($value) . '</pre></div>';
      }
    }
    $out .= '</div>';
  }
  $out .= '</details>';

  $out .= '<details class="tls-details"><summary>PEM</summary>';
  foreach ( $results["chain_order"] as $idx ) {
    $c = $results["certs"][$idx];
    $out .= '<h4>' . tls_role($c, $results) . ': ' . htmlentities($c["subject_cn"]) . '</h4><pre class="tls-pem">' . htmlentities($c["pem"]) . '</pre>';
  }
  $out .= '</details>';

  $host_arg = escapeshellarg($target);
  $out .= '<details class="tls-details"><summary>Verify yourself</summary>' .
    '<pre class="tls-cmd">echo | openssl s_client -showcerts' . ($sni_name != "" ? ' -servername ' . escapeshellarg($sni_name) : '') . ' -connect ' . htmlentities($host_arg) . ':' . intval($port) . ' 2>/dev/null | openssl x509 -noout -text</pre>' .
    '<pre class="tls-cmd">echo -n | gnutls-cli --print-cert -p ' . intval($port) . ($sni_name != "" ? ' --sni-hostname=' . escapeshellarg($sni_name) : '') . ' ' . htmlentities($host_arg) . ' | openssl x509 -noout -text</pre>' .
    '</details>';

  $out .= '</div>'; // tls-report
  return $out;
}
