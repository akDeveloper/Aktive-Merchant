<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

namespace AktiveMerchant\Billing\Gateways;

class MonerisUs extends Moneris
{
    const TEST_URL = 'https://esplusqa.moneris.com/gateway_us/servlet/MpgRequest';
    const LIVE_URL = 'https://esplus.moneris.com/gateway_us/servlet/MpgRequest';

    const API_VERSION = 'US PHP Api v.1.1.2';

    public static $supported_countries = array('US');

    public static $default_currency = 'USD';

    public function __construct(array $options = array())
    {
        parent::__construct($options);

        $this->authorize      = 'us_preauth';
        $this->cavv_authorize = 'us_cavv_preauth';
        $this->purchase       = 'us_purchase';
        $this->cavv_purchase  = 'us_cavv_purchase';
        $this->capture        = 'us_completion';
        $this->void           = 'us_purchasecorrection';
        $this->credit         = 'us_refund';
    }
}
