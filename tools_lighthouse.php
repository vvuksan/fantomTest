<?php

//////////////////////////////////////////////////////////////////////////////
// Rendering of a Lighthouse report (as returned by har-poon) for the
// waterfall tab. Relies on the wf_* formatting helpers in tools.php.
//////////////////////////////////////////////////////////////////////////////

# Audits shown as metric tiles, in display order, with a short label
function lh_metric_audits() {
  return array(
    "first-contentful-paint"   => "FCP",
    "largest-contentful-paint" => "LCP",
    "speed-index"              => "Speed Index",
    "total-blocking-time"      => "TBT",
    "cumulative-layout-shift"  => "CLS",
    "interactive"              => "TTI",
    "server-response-time"     => "TTFB",
  );
}

# Lighthouse's own thresholds: >= 0.9 good, >= 0.5 needs improvement, else poor
function lh_score_class($audit) {
  if ( !isset($audit["score"]) || $audit["score"] === null )
    return "na";
  if ( $audit["score"] >= 0.9 ) return "good";
  if ( $audit["score"] >= 0.5 ) return "avg";
  return "poor";
}

function lh_ms($ms) {
  return $ms >= 1000 ? sprintf("%.2f s", $ms / 1000) : round($ms) . " ms";
}

function lh_short_url($url) {
  $p = parse_url($url);
  if ( !isset($p["host"]) )
    return $url;
  $path = isset($p["path"]) ? $p["path"] : "/";
  if ( isset($p["query"]) ) $path .= "?" . $p["query"];
  return $p["host"] . $path;
}

# Format a single table cell according to the heading's value type
function lh_format_value($value, $type) {
  if ( $value === null || $value === "" )
    return "";
  switch ( $type ) {
    case "url":
    case "link":
    case "source-location":
      $extra = "";
      if ( is_array($value) ) {
        $extra = isset($value["line"]) ? ":" . intval($value["line"]) : "";
        $value = isset($value["url"]) ? $value["url"] : (isset($value["text"]) ? $value["text"] : "");
      }
      if ( !preg_match("/^https?:\/\//", $value) )
        return '<span class="ellipsis" title="' . htmlentities($value) . '">' . htmlentities($value) . '</span>';
      return '<a class="ellipsis" href="' . htmlentities($value) . '" title="' . htmlentities($value) . '" target="_blank" rel="noopener">' . htmlentities(lh_short_url($value)) . htmlentities($extra) . '</a>';
    case "bytes":
      return '<span class="mono">' . wf_format_bytes(floatval($value)) . '</span>';
    case "ms":
    case "timespanMs":
      return '<span class="mono">' . lh_ms(floatval($value)) . '</span>';
    case "node":
      if ( !is_array($value) ) return htmlentities((string)$value);
      $label = isset($value["nodeLabel"]) && $value["nodeLabel"] != "" ? $value["nodeLabel"] : (isset($value["snippet"]) ? $value["snippet"] : "");
      $tip = isset($value["selector"]) ? $value["selector"] : "";
      if ( isset($value["snippet"]) && $value["snippet"] != $label ) $tip .= "\n" . $value["snippet"];
      return '<span class="lh-node ellipsis" title="' . htmlentities(trim($tip)) . '">' . htmlentities(mb_substr($label, 0, 120)) . '</span>';
    case "numeric":
      return '<span class="mono">' . (is_numeric($value) ? htmlentities((string)round($value, 2)) : htmlentities((string)$value)) . '</span>';
    case "thumbnail":
      return "";
    default:
      if ( is_array($value) ) {
        if ( isset($value["value"]) ) $value = $value["value"];
        else if ( isset($value["text"]) ) $value = $value["text"];
        else $value = json_encode($value);
      }
      return '<span class="ellipsis" title="' . htmlentities((string)$value) . '">' . htmlentities((string)$value) . '</span>';
  }
}

# Render the detail rows of an audit as a compact table using Lighthouse's own headings
function lh_render_table($details, $max_rows = 8) {
  if ( !isset($details["items"]) || count($details["items"]) == 0 )
    return "";

  $headings = isset($details["headings"]) ? $details["headings"] : array();
  $columns = array();
  foreach ( $headings as $h ) {
    if ( !isset($h["key"]) || $h["key"] === null ) continue;
    $type = isset($h["valueType"]) ? $h["valueType"] : (isset($h["itemType"]) ? $h["itemType"] : "text");
    if ( $type == "thumbnail" ) continue;
    $label = isset($h["label"]) ? $h["label"] : (isset($h["text"]) ? $h["text"] : $h["key"]);
    if ( is_array($label) ) $label = isset($label["value"]) ? $label["value"] : $h["key"];
    $columns[] = array("key" => $h["key"], "type" => $type, "label" => $label);
    if ( count($columns) >= 5 ) break;
  }
  # Some diagnostics come without headings; synthesise from the first item's scalar keys
  if ( count($columns) == 0 ) {
    foreach ( $details["items"][0] as $k => $v ) {
      if ( is_scalar($v) ) $columns[] = array("key" => $k, "type" => "text", "label" => $k);
      if ( count($columns) >= 5 ) break;
    }
  }
  if ( count($columns) == 0 )
    return "";

  $out = '<table class="lh-table"><thead><tr>';
  foreach ( $columns as $c )
    $out .= '<th class="' . ($c["type"] == "bytes" || $c["type"] == "ms" || $c["type"] == "timespanMs" || $c["type"] == "numeric" ? "num" : "") . '">' . htmlentities($c["label"]) . '</th>';
  $out .= '</tr></thead><tbody>';

  $count = 0;
  $hidden = 0;
  foreach ( $details["items"] as $item ) {
    $count++;
    $row_class = $count > $max_rows ? ' class="lh-more" style="display:none"' : '';
    if ( $count > $max_rows ) $hidden++;
    $out .= '<tr' . $row_class . '>';
    foreach ( $columns as $c ) {
      $v = isset($item[$c["key"]]) ? $item[$c["key"]] : null;
      $num = in_array($c["type"], array("bytes", "ms", "timespanMs", "numeric"));
      $out .= '<td class="' . ($num ? "num" : ($c["type"] == "url" || $c["type"] == "node" ? "wide" : "")) . '">' . lh_format_value($v, $c["type"]) . '</td>';
    }
    $out .= '</tr>';
  }
  $out .= '</tbody></table>';
  if ( $hidden > 0 )
    $out .= '<button class="lh-showmore" onClick="$(this).prev(\'table\').find(\'.lh-more\').show(); $(this).remove(); return false;">Show ' . $hidden . ' more</button>';
  return $out;
}

# Pull the LCP element and phase breakdown out of the largest-contentful-paint-element audit
function lh_render_lcp_element($audit) {
  if ( !isset($audit["details"]["items"]) )
    return "";
  $node = null;
  $phases = array();
  foreach ( $audit["details"]["items"] as $table ) {
    if ( !isset($table["items"]) ) continue;
    foreach ( $table["items"] as $item ) {
      if ( isset($item["node"]) && $node === null ) $node = $item["node"];
      if ( isset($item["phase"]) ) $phases[] = $item;
    }
  }
  if ( $node === null && count($phases) == 0 )
    return "";

  $out = '<div class="lh-lcp"><span class="stat-label">Largest element</span>';
  if ( $node !== null ) {
    $label = isset($node["nodeLabel"]) && $node["nodeLabel"] != "" ? $node["nodeLabel"] : "";
    $snippet = isset($node["snippet"]) ? $node["snippet"] : "";
    $out .= '<div class="lh-lcp-node"><code>' . htmlentities(mb_substr($snippet, 0, 90)) . '</code>' .
      ($label != "" && $label != $snippet ? ' <span class="lh-lcp-label">“' . htmlentities(mb_substr($label, 0, 100)) . '”</span>' : '') . '</div>';
  }
  if ( count($phases) ) {
    $out .= '<div class="lh-phases">';
    foreach ( $phases as $p ) {
      $pct = isset($p["percent"]) ? floatval($p["percent"]) : 0;
      if ( $pct <= 0 ) continue;
      $phase_class = preg_replace("/[^a-z]/", "", strtolower($p["phase"]));
      $out .= '<span class="lh-phase ' . $phase_class . '" style="flex-basis:' . $pct . '%" title="' . htmlentities($p["phase"]) . ': ' . lh_ms($p["timing"]) . '"><i></i>' . htmlentities($p["phase"]) . ' <b>' . lh_ms($p["timing"]) . '</b></span>';
    }
    $out .= '</div>';
  }
  return $out . '</div>';
}

# Human description of the simulated environment Lighthouse used
function lh_environment_text($report) {
  $cs = isset($report["configSettings"]) ? $report["configSettings"] : array();
  $bits = array();
  if ( isset($cs["formFactor"]) ) $bits[] = "simulated " . $cs["formFactor"];
  if ( isset($cs["throttling"]["cpuSlowdownMultiplier"]) ) $bits[] = $cs["throttling"]["cpuSlowdownMultiplier"] . "× CPU slowdown";
  if ( isset($cs["throttling"]["rttMs"]) ) $bits[] = $cs["throttling"]["rttMs"] . " ms RTT";
  if ( isset($cs["throttling"]["throughputKbps"]) ) $bits[] = round($cs["throttling"]["throughputKbps"] / 1024, 1) . " Mbps";
  return "Lighthouse " . (isset($report["lighthouseVersion"]) ? $report["lighthouseVersion"] : "") . ", " . join(", ", $bits);
}

//////////////////////////////////////////////////////////////////////////////
// Main entry point
//////////////////////////////////////////////////////////////////////////////
function render_lighthouse_report($report) {

  $audits = isset($report["audits"]) ? $report["audits"] : array();
  $metric_ids = lh_metric_audits();

  $out = '<div id="audits" class="lh-report">';
  $out .= '<div class="lh-head"><span class="stat-label">Lighthouse lab metrics</span>' .
    '<span class="lh-env" title="Lab metrics come from a separate, throttled page load and are not comparable with the waterfall timings above">' . htmlentities(lh_environment_text($report)) . '</span></div>';

  # --- Metric tiles + screenshot ---
  $out .= '<div class="lh-body"><div class="lh-metrics">';
  foreach ( $metric_ids as $id => $label ) {
    if ( !isset($audits[$id]) ) continue;
    $a = $audits[$id];
    $value = isset($a["displayValue"]) ? str_replace("\xc2\xa0", " ", $a["displayValue"]) : "";
    # server-response-time says "Root document took 620 ms"; keep just the number
    if ( $id == "server-response-time" && isset($a["numericValue"]) ) $value = lh_ms($a["numericValue"]);
    $out .= '<div class="lh-tile ' . lh_score_class($a) . '" title="' . htmlentities($a["title"]) . '"><span class="lh-tile-label">' . $label . '</span><span class="lh-tile-value">' . htmlentities($value) . '</span></div>';
  }
  $out .= '</div>';

  if ( isset($report["fullPageScreenshot"]["screenshot"]["data"]) ) {
    $shot = $report["fullPageScreenshot"]["screenshot"];
    $out .= '<div class="lh-shot" title="Click to expand"><img src="' . $shot["data"] . '" alt="Full page screenshot" onClick="$(this).parent().toggleClass(\'expanded\')"></div>';
  }
  $out .= '</div>';

  if ( isset($audits["largest-contentful-paint-element"]) )
    $out .= lh_render_lcp_element($audits["largest-contentful-paint-element"]);

  # --- Opportunities and diagnostics ---
  $flagged = array();   # url => list of "audit title (savings)" for badges in the waterfall
  $opps = array();
  $passed = array();
  foreach ( $audits as $id => $a ) {
    # Metrics are tiles, the LCP element has its own block, and har-poon always adds the raw network-requests dump
    if ( isset($metric_ids[$id]) || $id == "largest-contentful-paint-element" || $id == "network-requests" ) continue;
    if ( !isset($a["scoreDisplayMode"]) || in_array($a["scoreDisplayMode"], array("notApplicable", "manual", "error")) ) continue;

    $items = isset($a["details"]["items"]) ? $a["details"]["items"] : array();
    $savings_bytes = isset($a["details"]["overallSavingsBytes"]) ? $a["details"]["overallSavingsBytes"] : 0;
    $savings_ms = isset($a["details"]["overallSavingsMs"]) ? $a["details"]["overallSavingsMs"] : 0;
    $has_issue = (isset($a["score"]) && $a["score"] !== null && $a["score"] < 1) || $savings_bytes > 0 || $savings_ms > 0 || ($a["scoreDisplayMode"] == "informative" && count($items) > 0);

    if ( !$has_issue ) {
      $passed[] = $a["title"];
      continue;
    }

    foreach ( $items as $item ) {
      if ( !isset($item["url"]) || !is_string($item["url"]) ) continue;
      $why = $a["title"];
      if ( isset($item["wastedBytes"]) && $item["wastedBytes"] > 0 ) $why .= ": " . wf_format_bytes($item["wastedBytes"]) . " wasted";
      else if ( isset($item["wastedMs"]) && $item["wastedMs"] > 0 ) $why .= ": " . lh_ms($item["wastedMs"]);
      $flagged[$item["url"]][] = $why;
    }

    $opps[] = array("audit" => $a, "sort" => max($savings_ms, $savings_bytes / 1000, (1 - (isset($a["score"]) ? floatval($a["score"]) : 0)) * 100));
  }
  usort($opps, function($x, $y) { return $y["sort"] <=> $x["sort"]; });

  if ( count($opps) ) {
    $out .= '<div class="lh-opps">';
    foreach ( $opps as $o ) {
      $a = $o["audit"];
      $cls = lh_score_class($a);
      $savings = array();
      if ( isset($a["details"]["overallSavingsBytes"]) && $a["details"]["overallSavingsBytes"] > 0 ) $savings[] = wf_format_bytes($a["details"]["overallSavingsBytes"]);
      if ( isset($a["details"]["overallSavingsMs"]) && $a["details"]["overallSavingsMs"] > 0 ) $savings[] = lh_ms($a["details"]["overallSavingsMs"]);
      $dv = isset($a["displayValue"]) ? str_replace("\xc2\xa0", " ", $a["displayValue"]) : "";
      $desc = isset($a["description"]) ? preg_replace("/\s*\[Learn[^\]]*\]\([^)]*\)\.?/", "", $a["description"]) : "";
      $table = isset($a["details"]) ? lh_render_table($a["details"]) : "";
      $out .= '<details class="lh-opp"' . ($cls == "poor" ? ' open' : '') . '><summary>' .
        '<span class="lh-dot ' . $cls . '"></span><b>' . htmlentities($a["title"]) . '</b>' .
        ($dv != "" ? '<span class="lh-dv">' . htmlentities($dv) . '</span>' : '') .
        (count($savings) ? '<span class="lh-savings">Est. savings ' . htmlentities(join(" / ", $savings)) . '</span>' : '') .
        '</summary><div class="lh-opp-body">' .
        ($desc != "" ? '<p class="lh-desc">' . htmlentities($desc) . '</p>' : '') . $table . '</div></details>';
    }
    $out .= '</div>';
  }

  if ( count($passed) )
    $out .= '<div class="lh-passed"><span class="lh-dot good"></span>Passed: ' . htmlentities(join(", ", $passed)) . '</div>';

  # --- Flag affected rows in the waterfall above ---
  if ( count($flagged) ) {
    $out .= '<script>(function(){var f=' . json_encode($flagged) . ';' .
      'document.querySelectorAll("#results tr.wf-row[data-url]").forEach(function(tr){var why=f[tr.getAttribute("data-url")];if(!why)return;' .
      'var b=tr.querySelector(".url-badges");if(!b)return;var s=document.createElement("span");s.className="pill lh-flag";s.title=why.join("\n");s.textContent="LH";b.appendChild(s);});})();</script>';
  }

  $out .= '</div>';
  return $out;
}
