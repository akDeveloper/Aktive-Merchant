<?php
require_once('../../lib/merchant.php');
require_once('../login.php');

Merchant_Billing_Base::mode('test');

$gateway = new Merchant_Billing_HsbcSecureEpayments(array(
    'login' => HSBC_LOGIN,
    'password' => HSBC_PASS,
    'client_id' => HSBC_CLIENT_ID,
    'currency' => 'EUR'
  )
);

$cc = new Merchant_Billing_CreditCard( array(
    "first_name" => "Test",
    "last_name" => "User",
    "number" => "4007000000027",
    "month" => "12",
    "year" => "2012",
    "verification_value" => "123"
  )
);

try {
  $response = $gateway->authorize('1', $cc);
  if ( $response->success() ) {
    echo 'Success authorize!';
  } else {
    echo $response->message();
  }
} catch (Exception $exc) {
  echo $exc->getMessage();
}


?>
