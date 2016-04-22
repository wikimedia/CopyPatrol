<?php
namespace Plagiabot\Web;
require __DIR__ . '/../vendor/autoload.php';
$db = parse_ini_file( '../replica.my.cnf' );
$plagiabot = new Plagiabot( $db );
$data = $plagiabot->run();
$html = '<tr class="container-fluid">
			<th class="col-md-1">Diff</th>
			<th class="col-md-1">Timestamp</th>
			<th class="col-md-2">Project</th>
			<th class="col-md-4">Page</th>
			<th class="col-md-1">Report</th>
			<th class="col-md-3">Wikiprojects</th>';
foreach ( $data as $k => $d ) {
	$html .= '<tr class="container-fluid">'
		. '<td class="col-md-1">' . $d['diff'] . '</td>'
		. '<td class="col-md-1">' . $d['timestamp'] . '</td>'
		. '<td class="col-md-2">' . $d['project'] . '</td>'
		. '<td class="col-md-4">' . $d['page'] . '</td>'
		. '<td class="col-md-1"><a href="' . $d['turnitin_report'] . '">Report</a></td>'
		. '<td class="col-md-3"><ul class="list-inline">';
	foreach ( $d['wikiprojects'] as $w ) {
		$html .= '<li>' . $w . '</li>';
	}
	$html .= '</ul></td></tr>';
}
?>

<!--@foff-->
<html>
	<head>
		<title>Plagiabot</title>
		<link href="css/bootstrap.min.css" rel="stylesheet">
		<script src="//tools-static.wmflabs.org/cdnjs/ajax/libs/jquery/1.12.0/jquery.min.js"></script>
		<script src="js/wikiprojects.js" type="text/javascript"></script>
		<script src="js/randomColor.js" type="text/javascript"></script>
		<script>
			$( document ).ready( function () {
				colorizeWikiprojects();
			});
		</script>
	</head>
	<body>
		<?php require_once( 'templates/header.php' ); ?>
	</body>
</html>
<!--@fon-->
