<?php
require_once('../../lib/merchant.php');

Merchant_Billing_Base::mode('test');
$gateway = new Merchant_Billing_Bogus();

$cc = new Merchant_Billing_CreditCard( array(
    "first_name" => "Test",
    "last_name" => "User",
    "number" => "1",
    "month" => "7",
    "year" => "2010",
    "verification_value" => "000"
  )
);

try {
  $response = $gateway->authorize(100, $cc); 
  echo $response->message();
} catch (Exception $e) {
  echo $e->getMessage();
}

?>
