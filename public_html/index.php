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
		$html .= '<tr class="trow">'
					.'<td>'. $wiki . '</td>'
					.'<td>'. $row['page_title'] . '</td>'
					.'<td><a href="'. $wiki . '/w/index.php?title=' . $row['page_title'] .'&diff='. $row['diff'].'">'. $row['diff'] .'</td>'
					.'<td>'. $row['diff_timestamp'] .'</td>'
				.'</tr>';
	}
}

echo $html;
?>
