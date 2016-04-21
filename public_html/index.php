<?php
namespace Plagiabot\Web;
require __DIR__ . '/../vendor/autoload.php';
$db = parse_ini_file( '../replica.my.cnf' );
$plagiabot = new Plagiabot( $db );
$data = $plagiabot->run();
$html = '<tr>
			<th>Diff</th>
			<th>Timestamp</th>
			<th>Project</th>
			<th>Page</th>
			<th>Report</th>
			<th>Wikiprojects</th>';
var_dump( $data );
foreach ( $data as $k => $d ) {
	$html .= '<tr>'
		. '<td>' . $d['diff'] . '</td>'
		. '<td>' . $d['timestamp'] . '</td>'
		. '<td>' . $d['project'] . '</td>'
		. '<td>' . $d['page'] . '</td>'
		. '<td>' . $d['report'] . '</td>';
	//. '<td>';
//	foreach ( $d['wikiprojects'] as $w ) {
//		$html .= '<div class="col-md-4">' . $w . '</div>';
//	}
	$html .= '</tr>';
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
			$(document).ready(function () {
				colorizeWikiprojects();
			});
		</script>
	</head>
	<body>
		<table class="table">
			<?= $html ?>
		</table>
	</body>
</html>
<!--@fon-->
