<html>
<head>
<title>Page performance</title>
<link type="text/css" href="css/flick/jquery-ui-1.8.14.custom.css" rel="stylesheet" />
<link type="text/css" href="css/fantomtest.css" rel="stylesheet" />
<script type="text/javascript" src="js/jquery-1.6.2.min.js"></script>
<script type="text/javascript" src="js/jquery-ui-1.8.14.custom.min.js"></script>
<script>
function getTimings() {
    window.location.hash = $("#checked_url").val();
    $("#results").html('<img src="img/spinner.gif">');
    $.get('waterfall.php', $("#query_form").serialize(), function(data) {
	$("#results").html(data);
     });
}
function getDns() {
    $("#dns_results").html('<img src="img/spinner.gif">');
    $.get('get_dns.php', $("#dns_form").serialize(), function(data) {
	$("#dns_results").html(data);
     });
}
function getPingMtr() {
    $("#pingmtr_results").html('<img src="img/spinner.gif">');
    $.get('get_mtr.php', $("#pingmtr_form").serialize(), function(data) {
	$("#pingmtr_results").html(data);
     });
}
</script>
</head>
<body>
<div id="tabs">
    <ul>
	<li><a href="#tab-waterfall">URL Test</a></li>
	<li><a href="#tab-dns">DNS</a></li>
	<li><a href="#tab-pingmtr">Ping/MTR</a></li>
    </ul>

<div id="tab-waterfall">
  <div id="large_screenshot">
  </div>
  <div id=header>
  
  <form id="query_form">
  <?php
  require_once('./conf.php');
  // If we define remotes create a select box
  if ( isset($conf['remotes']) and is_array($conf['remotes'] ) ) {
      print "Test from <select name='site_id'><option value='-1'>Local</option>";
      foreach ( $conf['remotes'] as $index => $remote ) {
	print "<option value='" . $index . "'>" . $remote['name'] . "</option>"; 
      }
      print "</select> ";
  } else {
    print "<input type=\"hidden\" name=\"site_id\" value=\"-1\">";
  }
  ?>
  URL <input id="checked_url" name="url" size=100>
  <button class="query_buttons" id="query_button" onclick="getTimings(); return false;">Get timings</button>
  <br />
  <input type="checkbox" name="include_image">Include page screenshot<br>
  </form>
  </div>
  <div id=results>
  </div>
</div>

<div id="tab-dns">
  <div id=header>
  
  <form id="dns_form">
  <?php
  require_once('./conf.php');
  // If we define remotes create a select box
  if ( isset($conf['remotes']) and is_array($conf['remotes'] ) ) {
      print "Test from <select name='site_id'>
      <option value='-100'>All Remotes</option>
      <option value='-1'>Local</option>";
      foreach ( $conf['remotes'] as $index => $remote ) {
	print "<option value='" . $index . "'>" . $remote['name'] . "</option>"; 
      }
      print "</select> ";
  } else {
    print "<input type=\"hidden\" name=\"site_id\" value=\"-1\">";
  }
  ?>
  Host name <input id="hostname" name="hostname" size=100>
  <button class="query_buttons" id="dns_querybutton" onclick="getDns(); return false;">Resolve</button>
  <br />
  </form>
  </div>
  <div id=dns_results>
  </div>
</div>

<div id="tab-pingmtr">
  <div id=header>
  
  <form id="pingmtr_form">
  <?php
  // If we define remotes create a select box
  if ( isset($conf['remotes']) and count($conf['remotes'] ) > 0 ) {
      print "Test from <select name='site_id'>
      <option value='-100'>All Remotes</option>
      <option value='-1'>Local</option>";
      foreach ( $conf['remotes'] as $index => $remote ) {
	print "<option value='" . $index . "'>" . $remote['name'] . "</option>"; 
      }
      print "</select> ";
  } else {
    print "<input type=\"hidden\" name=\"site_id\" value=\"-1\">";
  }
  ?>
  Host name <input id="hostname" name="hostname" size=100>
  <button class="query_buttons" id="dns_querybutton" onclick="getPingMtr(); return false;">Ping/MTR</button>
  <br />
  </form>
  </div>
  <div id=pingmtr_results>
  </div>
</div>


<script>
$(function(){
    $("#tabs").tabs();
    $(".query_buttons").button();

    $('#large_screenshot').dialog({
      title: "Large Picture",
      autoOpen: false,
      width: 800 });   

});
var myhash = window.location.hash;
if ( myhash != "" ) {
  $("#checked_url").val(myhash.replace("#",""));
  getTimings();
}
</script>
</body>
</html>
