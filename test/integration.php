<?php
require_once('../lib/merchant.php');

try {
  $options = array(
      'amount' => '50.00',
      'currency' => 'EUR',
      'service' => 'Eurobank'
      );
  $intergration = Merchant_Billing_Integration::payment_service_for('1000', 'test@test.com', $options);


  $intergration->billing_address(array('country'=>'Greece'))
                ->currency('EUR')
                ->customer(array('first_name' => 'John','last_name' => 'Doe'));

  Merchant_Logger::print_ar($intergration);
} catch (Exception $exc) {
  echo $exc->getMessage();
}

?>
