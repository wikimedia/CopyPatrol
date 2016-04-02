<?php

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

function getWikiprojects( $wiki, $page ) {
	$curl = curl_init();
	$url = $wiki . '/w/api.php?action=query&titles=Talk:' . $page . 'prop=templates&tllimit=max&formatversion=2';
	curl_setopt( $curl, CURLOPT_PUT, 1 );
	curl_setopt( $curl, CURLOPT_URL, $url );
	curl_setopt( $curl, CURLOPT_RETURNTRANSFER, 1 );
	$result = curl_exec( $curl );
	curl_close( $curl );
	echo $result;
    return $result;
}

echo $html;
?>
