<?php

class Merchant_Billing_CentinelCmpiLookupResponse extends Merchant_Billing_CentinelResponse {

  function __construct($success, $message, $params = array(), $options = array()) {
    parent::__construct($success, $message, $params, $options);
    $this->params['liability_shifted'] = $this->params['enrolled'] == 'N';
  }

  public function is_liability_shifted() {
    return $this->params['liability_shifted'];
  }

}

?>
