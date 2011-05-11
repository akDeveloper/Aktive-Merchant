<?php

require_once 'Payflow.php';

class Merchant_Billing_PayflowUk extends Merchant_Billing_Payflow
{
    protected $default_currency = 'GBP';
    protected $partner = 'PayPalUk';
    protected $supported_cardtypes = array('visa', 'master', 'american_express', 'discover', 'solo', 'switch');
    protected $supported_countries = array('GB');
    protected $homepage_url = 'https://www.paypal.com/uk/cgi-bin/webscr?cmd=_wp-pro-overview-outside';
    protected $display_name = 'PayPal Payflow Pro (UK)';
}

?>
