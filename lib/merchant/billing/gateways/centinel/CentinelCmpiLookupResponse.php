<?php

class Merchant_Billing_CentinelCmpiLookupResponse extends Merchant_Billing_CentinelResponse {

  function __construct() {
    parent::__construct();
    $this->params['liability_shifted'] = $this->params['enrolled'] == 'N';
  }

  public function is_liability_shifted() {
    return $this->params['liability_shifted'];
  }

}

?>
