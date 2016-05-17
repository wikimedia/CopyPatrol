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
				<div class="header-col col-md-3 text-center">Page</div>
				<div class="header-col col-md-1 text-center">Diff</div>
				<div class="header-col col-md-2 text-center">Editor</div>
				<div class="header-col col-md-3 text-center">Wikiprojects</div>
				<div class="header-col col-md-2 text-center">Review</div>
			</div>';
	foreach ( $data as $k => $d ) {
		$html .= '<div class="row-container container-fluid">
				<div class="row-div col-md-3 page-div">
					<b><a href="' . $d['page_link'] . '"';
		if ( $d['page_dead'] ) {
			$html .= 'class="text-danger"';
		}
		$html .= 'target="_blank">' . $d['page'] . '</a></b>
				</div>
				<div class="row-div col-md-1 text-center diff-div">
					<a href="' . $d['diff'] . '" target="_blank">Diff</a>
					<small><div>' . $d['timestamp'] . '</div></small>
				</div>
				<div class="row-div col-md-2 text-center report-div">';
		if ( $d['editor'] ) {
			$html .= '<a href="' . $d['editor_page'] . '"';
			if ( $d['user_page_dead'] ) {
				$html .= 'class="text-danger"';
			}
			$html .= ' target="_blank">' . $d['editor'] . '</a><br>
						<small>
							<a href="' . $d['editor_talk'] . '"';
			if ( $d['user_page_dead'] ) {
				$html .= 'class="text-danger"';
			}
			$html .= ' target="_blank">Talk</a> | 
							<a href="' . $d['editor_contribs'] . '" target="_blank">Contributions</a>
						<br>
						<div>Edit count: ' . $d['editcount'] . '</div></small>';
		} else {
			$html .= '<span class="glyphicon glyphicon-exclamation-sign"></span>
					  <div class="text-muted" data-toggle="tooltip" data-placement="bottom" 
						title="This usually means that the editor was anonymous. It may also mean that the data is not available in Labs database yet."> Editor not found
					  </div>';
		}
		$html .= '</div><div class="row-div col-md-3 text-center wikiproject-div"><center>';
		foreach ( $d['wikiprojects'] as $w ) {
			$html .= '<div class="row-div wproject">' . $w . '</div>';
		}
		$html .= '</center></div>';
		if ( $d['status'] == 'fixed' ) {
			$html .= '<div class="row-div col-md-2 text-center status-div">
						<input type="button" id=success' . $d['ithenticate_id'] . '  class="btn btn-success-clicked btn-block" title="The edit was a copyright violation and has been reverted" value="Page fixed" onclick="saveState(' . $d['ithenticate_id'] . ', \'Success\')">
						<input type="button" id=danger' . $d['ithenticate_id'] . ' class="btn btn-secondary btn-block" title="The edit is a false positive, nothing needs to be done" value="No action needed" onclick="saveState(' . $d['ithenticate_id'] . ', \'Danger\')" disabled>
					</div>';
		} elseif ( $d['status'] == 'falsepositive' ) {
			$html .= '<div class="row-div col-md-2 text-center status-div">
						<input type="button" id=success' . $d['ithenticate_id'] . '  class="btn btn-secondary btn-block" title="The edit was a copyright violation and has been reverted" value="Page fixed" onclick="saveState(' . $d['ithenticate_id'] . ', \'Success\')" disabled>
						<input type="button" id=danger' . $d['ithenticate_id'] . ' class="btn btn-danger-clicked btn-block" title="The edit is a false positive, nothing needs to be done" value="No action needed" onclick="saveState(' . $d['ithenticate_id'] . ', \'Danger\')">
					</div>';
		} else {
			$html .= '<div class="row-div col-md-2 text-center status-div">
						<input type="button" id=success' . $d['ithenticate_id'] . '  class="btn btn-success btn-block" title="The edit was a copyright violation and has been reverted" value="Page fixed" onclick="saveState(' . $d['ithenticate_id'] . ', \'Success\')">
						<input type="button" id=danger' . $d['ithenticate_id'] . ' class="btn btn-danger btn-block" title="The edit is a false positive, nothing needs to be done" value="No action needed" onclick="saveState(' . $d['ithenticate_id'] . ', \'Danger\')">
					</div>';
		}
		$html .= '</div><div class="compare-links-container">';
		$html .= '<a class="btn btn-xs btn-primary compare-button" href="' . $d['turnitin_report'] . '" target="_blank">
					<span class="glyphicon glyphicon-new-window"></span>
					Turnitin report
				</a><br>';
		foreach ( $d['copyvios'] as $key => $copyvio ) {
			$html .= '<button class="btn btn-xs btn-primary dropdown-toggle compare-button" onclick="compare( \'' . addslashes( $copyvio ) . '\', \'' . addslashes( $d['page_link'] ) . '\', \'' . $k . $key . '\')" >
						<span class="glyphicon glyphicon-chevron-down" aria-hidden="true"></span>
						Compare
					</button>
					<a href="' . $copyvio . '">' . $copyvio . '</a><br/>
					<div class="compare-div" id="comp' . $k . $key . '">
						<div class="compare-edit compare-pane">
							Lorem ipsum dolor sit amet, consectetuer adipiscing elit. Aenean commodo ligula eget dolor. Aenean massa. Cum sociis natoque penatibus et magnis dis parturient montes, nascetur ridiculus mus. Donec quam felis, ultricies nec, pellentesque eu, pretium quis, sem. Nulla consequat massa quis enim. Donec pede justo, fringilla vel, aliquet nec, vulputate eget, arcu. In enim justo, rhoncus ut, imperdiet a, venenatis vitae, justo. Nullam dictum felis eu pede mollis pretium. Integer tincidunt. Cras dapibus. Vivamus elementum semper nisi. Aenean vulputate eleifend tellus. Aenean leo ligula, porttitor eu, consequat vitae, eleifend ac, enim. Aliquam lorem ante, dapibus in, viverra quis, feugiat a, tellus. Phasellus viverra nulla ut metus varius laoreet. Quisque rutrum. Aenean imperdiet. Etiam ultricies nisi vel augue. Curabitur ullamcorper ultricies nisi. Nam eget dui. Etiam rhoncus. Maecenas tempus, tellus eget condimentum rhoncus, sem quam semper libero, sit amet adipiscing sem neque sed ipsum. Nam quam nunc, blandit vel, luctus pulvinar, hendrerit id, lorem. Maecenas nec odio et ante tincidunt tempus. Donec vitae sapien ut libero venenatis faucibus. Nullam quis ante. Etiam sit amet orci eget eros faucibus tincidunt. Duis leo. Sed fringilla mauris sit amet nibh. Donec sodales sagittis magna.
						</div>
						<div class="compare-website compare-pane">
							Lorem ipsum dolor sit amet, consectetuer adipiscing elit. Aenean commodo ligula eget dolor. Aenean massa. Cum sociis natoque penatibus et magnis dis parturient montes, nascetur ridiculus mus. Donec quam felis, ultricies nec, pellentesque eu, pretium quis, sem. Nulla consequat massa quis enim. Donec pede justo, fringilla vel, aliquet nec, vulputate eget, arcu. In enim justo, rhoncus ut, imperdiet a, venenatis vitae, justo. Nullam dictum felis eu pede mollis pretium. Integer tincidunt. Cras dapibus. Vivamus elementum semper nisi. Aenean vulputate eleifend tellus. Aenean leo ligula, porttitor eu, consequat vitae, eleifend ac, enim. Aliquam lorem ante, dapibus in, viverra quis, feugiat a, tellus. Phasellus viverra nulla ut metus varius laoreet. Quisque rutrum. Aenean imperdiet. Etiam ultricies nisi vel augue. Curabitur ullamcorper ultricies nisi. Nam eget dui. Etiam rhoncus. Maecenas tempus, tellus eget condimentum rhoncus, sem quam semper libero, sit amet adipiscing sem neque sed ipsum. Nam quam nunc, blandit vel, luctus pulvinar, hendrerit id, lorem. Maecenas nec odio et ante tincidunt tempus. Donec vitae sapien ut libero venenatis faucibus. Nullam quis ante. Etiam sit amet orci eget eros faucibus tincidunt. Duis leo. Sed fringilla mauris sit amet nibh. Donec sodales sagittis magna.
						</div>
					</div>';
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
		<script>
			$( document ).ready( function(){
				$( '[data-toggle="tooltip"]' ).tooltip();
			});
		</script>
	</head>
	<body>
		<?php require_once( 'templates/header.php' ); ?>
	</body>
</html>
<!--@fon-->
