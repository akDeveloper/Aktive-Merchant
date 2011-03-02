<?php
require_once('../../lib/merchant.php');
require_once('../login.php');

Merchant_Billing_Base::mode('test');
try {
  $gateway = new Merchant_Billing_PiraeusPaycenter( array(
    'acquire_id' => acquire_id,
    'merchant_id' => merchant_id,
    'pos_id' => pos_id,
    'user' => user,
    'password' => e_password,
    'channel_type' => channel_type
  ));

  $cc = new Merchant_Billing_CreditCard( array(
      "first_name" => "Test",
      "last_name" => "User",
      "number" => "4111111111111111",
      "month" => "01",
      "year" => "2011",
      "verification_value" => "123"
    )
  );

  $options = array(
      'order_id' => $gateway->generate_unique_id()
  );

  $response = $gateway->purchase('1', $cc, $options);
  Merchant_Logger::print_ar($response);
  if ( $response->success() ) {
    echo 'Success Authorize<br />';
    echo $response->message()."<br />";
  } else {
    echo $response->message();
  }
} catch (Exception $e) {
  echo $e->getMessage();
}
?>