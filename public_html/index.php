<?php

require __DIR__ . '/../vendor/autoload.php';

$db = parse_ini_file( '../replica.my.cnf' );

// Link to plagiabot database
$linkPl = mysqli_connect( 'enwiki.labsdb', $db['user'], $db['password'], 's51306__copyright_p' );
// Link to wikiproject database
$linkWp = mysqli_connect( 'labsdb1004.eqiad.wmnet', $db['user'], $db['password'], 's52475__wpx_p' );

$queryPl = "SELECT * FROM copyright_diffs ORDER BY diff_timestamp DESC LIMIT 30";
$resultPl = mysqli_query( $linkPl, $queryPl );

$html = '<table class="table">';
$html .= '<tr>
			<th>Wiki</th>
			<th>Page</th>
			<th>Diff</th>
			<th>Date</th>
			<th>Wikiprojects</th>
		</tr>';

if ( $resultPl->num_rows > 0 ) {
	$editProjects = array();
	$editDiff = array();
	$editPage = array();
	$editWiki = array();
	$editTime = array();
	while ( $row = $resultPl->fetch_assoc() ) {
		$editWiki[] = 'https://' . $row['lang'] . '.' . $row['project'] . '.org';
		$editTime[] = $row['diff_timestamp'];
		$editPage[] = $row['page_title'];
		$editDiff[] = $row['diff'];
		$editProjects[] = getWikiprojects( $row['page_title'], $linkWp );
	}

	for( $i = 0; $i < count( $editPage ); $i++ ) {
		$pageLink = $editWiki[$i] . '/w/index.php?title=' . $editPage[$i];
		$html .= '<tr class="trow">'
					.'<td>'. $editWiki[$i] . '</td>'
					.'<td><a href="' . $pageLink . '">' . $editPage[$i] . '</td>'
					.'<td><a href="' . $pageLink . '&diff='. $editDiff[$i] .'">'. $editDiff[$i] .'</td>'
					.'<td>'. $editTime[$i] .'</td><td>';
		foreach ( $editProjects[$i] as $project ) {
			$html .= '<div class="wp">'. $project .'</div>';
		}
		$html .= '<td></tr>';
	}
	$html .= '</table>';
}

// TODO: Make this function work with different wikis when the time comes
function getWikiprojects( $page, $link ) {
	$q = "SELECT * FROM projectindex WHERE pi_page = 'Talk:". $page ."'";
	$r = mysqli_query( $link, $q );
	$result = array();
	if ( $r->num_rows > 0 ) {
		while ( $row = $r->fetch_assoc() ) {
			$result[] = $row['pi_project'];
		}
	}
	return $result;
}

?>
<html>
	<head>
		<title>Plagiabot</title>
		<link href="css/bootstrap.min.css" rel="stylesheet">
		<script src="//tools-static.wmflabs.org/cdnjs/ajax/libs/jquery/1.12.0/jquery.min.js"></script>
		<script src="js/wikiprojects.js" type="text/javascript"></script>
		<script src="js/randomColor.js" type="text/javascript"></script>
		<script>
		$( document ).ready( function(){
			colorizeWikiprojects();
		});
		</script>
	<body>
		<?php echo $html; ?>
	</body>
</html>
