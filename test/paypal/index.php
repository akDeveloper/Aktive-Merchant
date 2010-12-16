<?php
require_once('../../lib/merchant.php');
require_once('../login.php');

Merchant_Billing_Base::mode('test');

$gateway = new Merchant_Billing_Paypal( array(
  'login' => PAYPAL_PRO_LOGIN,
  'password' => PAYPAL_PRO_PASS,
  'signature' => PAYPAL_PRO_SIG,
  'currency' => 'USD'
  )
);

$cc = new Merchant_Billing_CreditCard( array(
    "first_name" => "Test",
    "last_name" => "User",
    "number" => "4242500628981382",
    "month" => "9",
    "year" => "2019",
    "verification_value" => "123"
  )
);

$options = array(
  'address' => array(
    'address1' => '1 Main St',
    'zip' => '95131',
    'state' => 'CA',
    'country' => 'United States',
    'city' => 'San Jose'
  )
);

try {

  $response = $gateway->authorize(10,$cc,$options);

  if ( $response->success() ) {
    echo 'Success payment!';
  } else {
    echo $response->message();
  }
} catch (Exception $exc) {
  echo $exc->getMessage();
}

?>
