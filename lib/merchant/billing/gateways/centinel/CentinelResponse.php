<?php 

class Merchant_Billing_CentinelResponse extends Merchant_Billing_Response {

  public function message() {
    if ($this->enrolled == 'N')
      return 'Cardholder not enrolled! ';
    return $this->error_no . ": " . $this->message;
  } 

}

?>
