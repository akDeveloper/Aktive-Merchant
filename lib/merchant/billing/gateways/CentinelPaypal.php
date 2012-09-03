<?php

require_once 'Centinel.php';

class Merchant_Billing_Centinel_Paypal extends Merchant_Billing_Centinel {
  protected $live_url = 'https://paypal.cardinalcommerce.com/maps/txns.asp';
}

?>
