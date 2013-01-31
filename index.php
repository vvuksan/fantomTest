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
</script>
</head>
<body>
<div id="tabs">
    <ul>
	<li><a href="#tab-waterfall">URL Test</a></li>
    </ul>

<div id="tab-waterfall">
<div id="large_screenshot">
</div>
<div id=header>

<form id="query_form">
<?php
require_once('./conf.php');
if ( isset($conf['remotes']) and is_array($conf['remotes'] ) ) {
    print "Test from <select name='site_id'><option value='-1'>Local</option>";
    foreach ( $conf['remotes'] as $index => $remote ) {
      print "<option value='" . $index . "'>" . $remote['name'] . "</option>"; 
    }
    print "</select> ";
}
?>
URL <input id="checked_url" name="url" size=100>
<button id="query_button" onclick="getTimings(); return false;">Get timings</button>
<br />
<input type="checkbox" name="include_image">Include page screenshot<br>
</form>
</div>
<div id=results>
</div>
</div>
<script>
$(function(){
    $("#tabs").tabs();
    $("#query_button").button();

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
