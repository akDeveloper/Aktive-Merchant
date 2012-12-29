<?php
date_default_timezone_set('UTC');

require_once 'SplClassLoader.php';
$loader = new SplClassLoader('AktiveMerchant', __DIR__ . '/lib');
$loader->register();

?>
