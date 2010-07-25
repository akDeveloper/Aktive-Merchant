<?php
require_once('../../lib/merchant.php');
require_once('../login.php');

Merchant_Billing_Base::mode('test'); # Remove this on production mode

#Alternative way to get a gateway instanse.
$gateway = Merchant_Billing_Base::gateway('authorize_net', array(
  'login' => AUTH_NET_LOGIN,
  'password' => AUTH_NET_PASS));

$cc = new Merchant_Billing_CreditCard( array(
    "first_name" => "John",
    "last_name" => "Doe",
    "number" => "4111111111111111",
    "month" => "01",
    "year" => "2015",
    "verification_value" => "000"
  )
);

$options = array(
  'order_id' => 'REF' . $gateway->generate_unique_id(),
  'description' => '',
  'length' => '1',
  'unit' => 'months',
  'start_date' => '2010-09-11',
  'occurrences' => '10',
  'billing_address' => array(
    'first_name' => 'John',
    'last_name' => 'Doe',
    'address1' => '1234 Street',
    'zip' => '98004',
    'state' => 'WA'
  )
);

try {
  if( false == $cc->is_valid() ) {
    var_dump($cc->errors());
  } else {
    $response = $gateway->recurring("1.00",$cc,$options);
    if ( $response->success() ) {
      echo " Subscription id: " . $response->subscription_id;
    } else {
      echo $response->message();
    }
  }

} catch (Exception $e) {
  echo $e->getMessage();
}
?>