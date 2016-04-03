<?php

require __DIR__ . '/../vendor/autoload.php';

use Mediawiki\Api\MediawikiApi;
use Mediawiki\Api\FluentRequest;

$db = parse_ini_file( '../replica.my.cnf' );

$link = mysqli_connect( 'enwiki.labsdb', $db['user'], $db['password'], 's51306__copyright_p' );

$query = "SELECT * FROM copyright_diffs ORDER BY diff_timestamp DESC LIMIT 10";

$result = mysqli_query( $link, $query );

$html = '<table>';
$html .= '<tr>
			<th>Wiki</th>
			<th>Page</th>
			<th>Diff</th>
			<th>Date</th>
		</tr>';

if ( $result->num_rows > 0 ) {
	while ( $row = $result->fetch_assoc() ) {
		$wiki = "https://" . $row['lang'] . '.' . $row['project'] . '.org';
		$wikiprojects = getWikiprojects( $wiki, $row['page_title'] );
		$html .= '<tr class="trow">'
					.'<td>'. $wiki . '</td>'
					.'<td>'. $row['page_title'] . '</td>'
					.'<td><a href="'. $wiki . '/w/index.php?title=' . $row['page_title'] .'&diff='. $row['diff'].'">'. $row['diff'] .'</td>'
					.'<td>'. $row['diff_timestamp'] .'</td>'
				.'</tr>';
	}
}

echo $html;

function getWikiprojects( $wiki, $page ) {
	$api = MediawikiApi::newFromPage( $wiki . '/wiki/Talk:' . $page );
	$projects = array();
	$queryResponse = $api->getRequest(
		FluentRequest::factory()->setAction( 'query' )
			->setParam( 'prop', 'templates' )
			->setParam( 'titles', 'Talk:' . $page )
			->setParam( 'tllimit', 'max' )
			->setParam( 'formatversion', 2 )
		);
	foreach( $queryResponse['query']['pages'][0]['templates'] as $key => $value ){
		if( strpos( $value['title'], "Template:WikiProject " ) !== false ) {
			if( strpos( $value['title'], "/") == false ) {
				$projects[] = $value['title'];
			}
		}
	};
	var_dump( $projects );
}

?>
