<?php

require_once dirname(__FILE__) . '/../config.php';

$link = mysqli_connect( $credentials['host'], $credentials['user'], $credentials['pass'], $credentials['db'] );

echo 'Hello, I\'m plagiabot!';

$query = "SELECT id, project, page_title, report FROM copyright_diffs LIMIT 10";

$result = mysqli_query( $link, $query );

if ( $result->num_rows > 0 ) {
	while ( $row = $result->fetch_assoc() ) {
		echo $row['id'], $row['project'], $row['page_title'], $row['report'];
	}
}
