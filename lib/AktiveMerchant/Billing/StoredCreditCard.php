<?php

namespace AktiveMerchant\Billing;

class StoredCreditCard extends CreditCard {
  protected $billing_id;
  function __construct($billing_id) {
    $this->billing_id = $billing_id;
  }

  function billing_id() {
    return $this->billing_id;
  }

}
