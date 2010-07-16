<?php
require_once('../../lib/merchant.php');
require_once('../login.php');

Merchant_Billing_Base::mode('test');
try {
  $gateway = new Merchant_Billing_Eurobank( array(
    'login' => EUROBANK_LOGIN,
    'password' => EUROBANK_PASS
  ));

  $cc = new Merchant_Billing_CreditCard( array(
      "first_name" => "Test",
      "last_name" => "User",
      "number" => "41111111111111",
      "month" => "12",
      "year" => "2012",
      "verification_value" => "123"
    )
  );
  Merchant_Logger::print_ar($gateway->authorize('1', $cc, array('customer_email'=>'test@test.com')));
} catch (Exception $e) {
  echo $e->getMessage();
}
?>
