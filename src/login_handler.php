<?php
/**
 * Created by PhpStorm.
 * User: nkohli
 * Date: 5/19/16
 * Time: 6:02 PM
 */
namespace Plagiabot\Web;

require __DIR__ . '/../vendor/autoload.php';
$login = new LoginUser();
$ret = $login->execute();
echo 'hulala';