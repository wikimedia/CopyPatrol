<?php

require __DIR__ . '/../vendor/autoload.php';

use Mediawiki\Api\MediawikiApi;
use Mediawiki\Api\FluentRequest;
use GuzzleHttp\Promise\Promise;

$db = parse_ini_file( '../replica.my.cnf' );

$link = mysqli_connect( 'enwiki.labsdb', $db['user'], $db['password'], 's51306__copyright_p' );

$query = "SELECT * FROM copyright_diffs ORDER BY diff_timestamp DESC LIMIT 40";

$result = mysqli_query( $link, $query );

$html = '<table>';
$html .= '<tr>
			<th>Wiki</th>
			<th>Page</th>
			<th>Diff</th>
			<th>Date</th>
		</tr>';

var_dump( $result );

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
		// $html .= '<tr class="trow">'
		// 			.'<td>'. $wiki . '</td>'
		// 			.'<td>'. $row['page_title'] . '</td>'
		// 			.'<td><a href="'. $wiki . '/w/index.php?title=' . $row['page_title'] .'&diff='. $row['diff'].'">'. $row['diff'] .'</td>'
		// 			.'<td>'. $row['diff_timestamp'] .'</td>'
		// 		.'</tr>';
		// echo $row['page_title'], $wikiprojects;
	}

	$editProjects = getWikiprojects( $editWiki, $editPage );
	var_dump( $editProjects );
}

// $wikis = array( 'https://en.wikipedia.org', 'https://en.wikipedia.org', 'https://en.wikipedia.org' );
// $pages = array( 'Donald Trump', 'Professor Green', 'Donald Duck' );
// getWikiprojects( $wikis, $pages );

function getWikiprojects( $wikis, $pages ) {
	$api = MediawikiApi::newFromApiEndpoint( 'http://en.wikipedia.org/w/api.php' );
	$requestPromises = array();
	for ( $i=0;  $i < count($pages);  $i++ ) {
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
