<?php

namespace AktiveMerchant\Billing\Gateways\PaymentExpress;

class Response extends \AktiveMerchant\Billing\Response {
  # add a method to $response so we can easily get the token
  # for Validate transactions
  function token() {
    return $this->params["billing_id"] ?: $this->params["dps_billing_id"];
  }
}
