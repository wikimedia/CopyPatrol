<?php
namespace Plagiabot\Web;
require __DIR__ . '/../vendor/autoload.php';
$db = parse_ini_file( '../replica.my.cnf' );
$plagiabot = new Plagiabot( $db );
$data = $plagiabot->run();
if ( $data === false ) {
	$html = 'No records found or there was a connection error';
} else {
	$html = '<div class="header-div container-fluid">
				<div class="header-col col-md-2 text-center">Page</div>
				<div class="header-col col-md-2 text-center">Diff</div>
				<div class="header-col col-md-2 text-center">Editor</div>
				<div class="header-col col-md-3 text-center">Wikiprojects</div>
				<div class="header-col col-md-2" text-center">Review</div>
			</div>';
	foreach ( $data as $k => $d ) {
		$html .= '<div class="row-container container-fluid">
				<div class="row-div col-md-2 page-div">
					<b><a href="' . $d['page_link'] . '" target="_blank">' . $d['page'] . '</a></b>
				</div>
				<div class="row-div col-md-2 text-center diff-div">
					<a href="' . $d['diff'] . '" target="_blank">Diff</a>
					<div>' . $d['timestamp'] . '</div>
				</div>
				<div class="row-div col-md-2 text-center report-div">
					<a href="' . $d['turnitin_report'] . '" target="_blank">Report</a>
					<a href="#">Editor name</a>
					<a href="#">talk and contribs</a>
					<a href="#">Edit count</a>
				</div>
				<div class="row-div col-md-3 text-center wikiproject-div">';
		foreach ( $d['wikiprojects'] as $w ) {
			$html .= '<div class="row-div wproject">' . $w . '</div>';
		}
		$html .= '</div>';
		if ( $d['status'] == 'fixed' ) {
			$html .= '<div class="row-div col-md-2 text-center status-div">
						<input type="button" id=success' . $d['ithenticate_id'] . '  class="btn btn-success-clicked btn-block" title="The edit was a copyright violation and has been reverted" value="Page fixed" onclick="saveState(' . $d['ithenticate_id'] . ', \'Success\')">
						<input type="button" id=danger' . $d['ithenticate_id'] . ' class="btn btn-secondary btn-block" title="The edit is a false positive, nothing needs to be done" value="No action needed" onclick="saveState(' . $d['ithenticate_id'] . ', \'Danger\')" disabled>
					</div>';
		} elseif ( $d['status'] == 'falsepositive' ) {
			$html .= '<div class="row-div col-md-2 text-center">
						<input type="button" id=success' . $d['ithenticate_id'] . '  class="btn btn-secondary btn-block" title="The edit was a copyright violation and has been reverted" value="Page fixed" onclick="saveState(' . $d['ithenticate_id'] . ', \'Success\')" disabled>
						<input type="button" id=danger' . $d['ithenticate_id'] . ' class="btn btn-danger-clicked btn-block" title="The edit is a false positive, nothing needs to be done" value="No action needed" onclick="saveState(' . $d['ithenticate_id'] . ', \'Danger\')">
					</div>';
		} else {
			$html .= '<div class="row-div col-md-2 text-center">
						<input type="button" id=success' . $d['ithenticate_id'] . '  class="btn btn-success btn-block" title="The edit was a copyright violation and has been reverted" value="Page fixed" onclick="saveState(' . $d['ithenticate_id'] . ', \'Success\')">
						<input type="button" id=danger' . $d['ithenticate_id'] . ' class="btn btn-danger btn-block" title="The edit is a false positive, nothing needs to be done" value="No action needed" onclick="saveState(' . $d['ithenticate_id'] . ', \'Danger\')">
					</div>';
		}
		$html .= '</div><div class="compare-links-container">';
		foreach ( $d['copyvios'] as $key => $copyvio ) {
			$html .= '<button class="btn btn-default dropdown-toggle compare-button" onclick="compare( \'' . htmlspecialchars( $copyvio ) . '\', \'' . htmlspecialchars( $d['page_link'] ) . '\', \'' . $k . $key . '\')" >
						<span class="glyphicon glyphicon-chevron-down" aria-hidden="true"></span>
					Compare</button>
					<a href="' . $copyvio . '">' . htmlspecialchars( $copyvio ) . '</a><br/>
					<div class="compare-div" id="comp' . $k . $key . '">Testing if this works....</div>';
		}
		$html .= '</div>';
	}
}
?>

<!--@foff-->
<html>
	<head>
		<title>Plagiabot</title>
		<link href="//tools-static.wmflabs.org/cdnjs/ajax/libs/twitter-bootstrap/3.3.6/css/bootstrap.min.css" rel="stylesheet">
		<link href="css/index.css" rel="stylesheet">
		<script src="//tools-static.wmflabs.org/cdnjs/ajax/libs/jquery/1.12.0/jquery.min.js"></script>
		<script src="//tools-static.wmflabs.org/cdnjs/ajax/libs/twitter-bootstrap/3.3.6/js/bootstrap.min.js"></script>
		<script src="js/saveResult.js" type="text/javascript"></script>
		<script src="js/compare.js" type="text/javascript"></script>
	</head>
	<body>
		<?php require_once( 'templates/header.php' ); ?>
	</body>
</html>
<!--@fon-->
