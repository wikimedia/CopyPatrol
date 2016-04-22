<?php
namespace Plagiabot\Web;
require __DIR__ . '/../vendor/autoload.php';
$db = parse_ini_file( '../replica.my.cnf' );
$plagiabot = new Plagiabot( $db );
$data = $plagiabot->run();
$html = '<tr class="container-fluid">
			<th class="col-md-1 text-center">Diff</th>
			<th class="col-md-1 text-center">Timestamp</th>
			<th class="col-md-4 text-center">Page</th>
			<th class="col-md-2 text-center">Turnitin Report</th>
			<th class="col-md-3 text-center">Wikiprojects</th>';
foreach ( $data as $k => $d ) {
	$html .= '<tr class="container-fluid">'
		. '<td class="col-md-1 text-center"><a href="' . $d['diff'] . '" target="_blank">Diff</a></td>'
		. '<td class="col-md-1 text-center">' . $d['timestamp'] . '</td>'
		. '<td class="col-md-4"><a href="' . $d['page_link'] . '" target="_blank">' . $d['page'] . '</a></td>'
		. '<td class="col-md-2 text-center"><a href="' . $d['turnitin_report'] . '" target="_blank">Report</a></td>'
		. '<td class="col-md-3 text-center">';
	foreach ( $d['wikiprojects'] as $w ) {
		$html .= '<div class="wproject">' . $w . '</div>';
	}
	$html .= '</td></tr>';
}
?>

<!--@foff-->
<html>
	<head>
		<title>Plagiabot</title>
		<link href="css/bootstrap.min.css" rel="stylesheet">
		<link href="css/index.css" rel="stylesheet">
		<script src="//tools-static.wmflabs.org/cdnjs/ajax/libs/jquery/1.12.0/jquery.min.js"></script>
		<script src="js/bootstrap.min.js"></script>
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
