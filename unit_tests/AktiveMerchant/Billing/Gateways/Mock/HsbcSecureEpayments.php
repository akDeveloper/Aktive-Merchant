<?php

namespace AktiveMerchant\Billing\Gateways\Mock;

class HsbcSecureEpayments extends \AktiveMerchant\Billing\Gateways\HsbcSecureEpayments
{
    protected $methods = array();

    public function expects($method_name, $return)
    {
        $this->methods[$method_name] = $return;
    }

    protected function ssl_post($endpoint, $data, $options = array())
    {
        return array_key_exists(__FUNCTION__, $this->methods) 
            ? $this->methods[__FUNCTION__]
            : parent::ssl_post($endpoint, $data, $options);
    }

}
