<?php
namespace Plagiabot\Web;
require __DIR__ . '/../vendor/autoload.php';
$db = parse_ini_file( '../replica.my.cnf' );
$plagiabot = new Plagiabot( $db );
$value = $_POST['value'];
$id = $_POST['id'];
if ( $value == 'Success' ) {
	$value = 'fixed';
} elseif ( $value == 'Warning' ) {
	$value = 'possiblecopyvio';
} elseif ( $value == 'Danger' ) {
	$value = 'falsepositive';
} else {
	$value = '';
}
$data = $plagiabot->insertCopyvioAssessment( $id, $value );
if ( $data ) {
	// Only for testing purposes
	echo $id . ' ' . $value . ' true ';
} else {
	// Only for testing purposes
	echo $id . ' ' . $value . ' false ';
}
?>


