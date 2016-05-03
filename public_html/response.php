<?php
namespace Plagiabot\Web;
require __DIR__ . '/../vendor/autoload.php';
$db = parse_ini_file( '../replica.my.cnf' );
$plagiabot = new Plagiabot( $db );

$value = $_POST['value'];
$id = $_POST['id'];

$data = $plagiabot->insertCopyvioAssessment( $id, $value );

if ( $data ) {
	echo true;
} else {
	echo false;
}

?>


