<?php
require_once('../../lib/merchant.php');

Merchant_Billing_Base::mode('test');
$gateway = new Merchant_Billing_Bogus();

$cc = new Merchant_Billing_CreditCard( array(
    "first_name" => "Test",
    "last_name" => "User",
    "number" => "2",
    "month" => "7",
    "year" => "2010",
    "verification_value" => "000"
  )
);

try {
  
} catch (Exception $e) {
  echo $e->getMessage();
}

?>
