<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

namespace AktiveMerchant\Billing\Gateways;

class MonerisUs extends Moneris
{
    const TEST_URL = 'https://esplusqa.moneris.com/gateway_us/servlet/MpgRequest';
    const LIVE_URL = 'https://esplus.moneris.com/gateway_us/servlet/MpgRequest';

    const API_VERSION = 'US PHP Api v.1.1.2';

    const AUTHORIZE = 'us_preauth';
    const CAVV_ATHORIZE = 'us_cavv_preauth';
    const PURCHASE = 'us_purchase';
    const CAVV_PURCHASE = 'us_cavv_purchase';
    const CAPTURE = 'us_completion';
    const VOID = 'us_purchasecorrection';
    const CREDIT = 'us_refund';

    public static $supported_countries = array('US');

    public static $default_currency = 'USD';
}
