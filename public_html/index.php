<?php

require __DIR__ . '/../vendor/autoload.php';

use Mediawiki\Api\MediawikiApi;
use Mediawiki\Api\FluentRequest;
use GuzzleHttp\Promise\Promise;

$db = parse_ini_file( '../replica.my.cnf' );

$link = mysqli_connect( 'enwiki.labsdb', $db['user'], $db['password'], 's51306__copyright_p' );

$query = "SELECT * FROM copyright_diffs ORDER BY diff_timestamp DESC LIMIT 10";

$result = mysqli_query( $link, $query );

$allProjects = array();

$html = '<table>';
$html .= '<tr>
			<th>Wiki</th>
			<th>Page</th>
			<th>Diff</th>
			<th>Date</th>
			<th>Wikiprojects</th>
		</tr>';

if ( $result->num_rows > 0 ) {
	$editProjects = array();
	$editDiff = array();
	$editPage = array();
	$editWiki = array();
	$editTime = array();
	while ( $row = $result->fetch_assoc() ) {
		$editWiki[] = 'https://' . $row['lang'] . '.' . $row['project'] . '.org';
		$editTime[] = $row['diff_timestamp'];
		$editPage[] = $row['page_title'];
		$editDiff[] = $row['diff'];
	}
	$editProjects = getWikiprojects( $editWiki, $editPage );

	for( $i = 0; $i < count( $editPage ); $i++ ) {
		$html .= '<tr class="trow">'
					.'<td>'. $editWiki[$i] . '</td>'
					.'<td>'. $editPage[$i] . '</td>'
					.'<td><a href="'. $editWiki[$i] . '/w/index.php?title=' . $editPage[$i] .'&diff='. $editDiff[$i] .'">'. $editDiff[$i] .'</td>'
					.'<td>'. $editTime[$i] .'</td><td>';
		foreach ( $editProjects[$editPage[$i]] as $key => $value ) {
			$html .= $value . ', ';
		}
		$html .= '</td></tr>';
	}
	$html .= '</table>';
}

function getWikiprojects( $wikis, $pages ) {
	$api = MediawikiApi::newFromApiEndpoint( 'http://en.wikipedia.org/w/api.php' );
	$requestPromises = array();
	for ( $i=0;  $i < count( $pages );  $i++ ) {
		$requestPromises[$pages[$i]] = $api->getRequestAsync( FluentRequest::factory()->setAction( 'query' )
			->setParam( 'prop', 'templates' )
			->setParam( 'titles', 'Talk:' . $pages[$i] )
			->setParam( 'tllimit', 'max' )
			->setParam( 'formatversion', 2 )
		);
	}
	$requestPromises;

	$results = GuzzleHttp\Promise\unwrap( $requestPromises );

	$projects = array();
	foreach ( $results as $key1 => $value1 ) {
		foreach ( $value1['query']['pages'][0]['templates'] as $key2 => $value2 ) {
			if( strpos( $value2['title'], "Template:WikiProject " ) !== false ) {
				if( strpos( $value2['title'], "/") == false ) {
					$projects[$key1][] = $value2['title'];
				}
			}
		}
	}
	return $projects;
}

?>

<!--
<html>
	<head>
		<title>IA bot logs</title>
		<link href="../vendor/twbs/bootstrap/bootstrap.min.css" rel="stylesheet">
	</head>
<body>
	<?=$html?>
</body>
</html> -->
