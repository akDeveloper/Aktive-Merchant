<?php
require_once('../autoload.php');

use AktiveMerchant\Billing\Integrations\Integration;

try {
  $options = array(
      'amount' => '50.00',
      'currency' => 'EUR',
      'service' => 'Eurobank'
      );
  $intergration = Integration::payment_service_for('1000', 'test@test.com', $options);


  $intergration->billing_address(array('country'=>'Greece'))
                ->currency('EUR')
                ->customer(array('first_name' => 'John','last_name' => 'Doe'));

  print_r($intergration);
  echo $intergration->to_html();
} catch (Exception $exc) {
  echo $exc->getMessage();
}

?>
