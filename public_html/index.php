<?php

$db = parse_ini_file( '/../replica.my.cnf' );

$link = mysqli_connect( 'enwiki.labsdb', $db['user'], $db['password'], 's51396__copyright_p' );

echo $db['user'];

$query = "SELECT * FROM copyright_diffs LIMIT 10";

echo $query;

$result = mysqli_query( $link, $query );

var_dump( $result );

if ( $result->num_rows > 0 ) {
	while ( $row = $result->fetch_assoc() ) {
		echo $row['id'], $row['project'], $row['page_title'], $row['report'];
	}
}


?>
