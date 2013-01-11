<?php
require_once('../../autoload.php');
use AktiveMerchant\Billing\Base;
use AktiveMerchant\Billing\Gateways\Moneris;

session_start();

Base::mode('test'); #Remove this line on production mode

try {

    $gateway = new Moneris( array(
        'store_id' => 'store1', #change this
        'api_token' => 'yesguy', #change this
        'region' => 'CA' #change this
    )
);
} catch (Exception $exc) {
    echo $exc->getMessage();
}
