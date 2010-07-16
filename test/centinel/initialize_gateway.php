<?php 
session_start();

require_once('../login.php');

Merchant_Billing_Base::mode('test');

$gateway = new Merchant_Billing_Centinel(array(
    'login' => CENTINEL_LOGIN,
    'password' => CENTINEL_PASS,
    'processor_id' => CENTINEL_PROC
));

?>
