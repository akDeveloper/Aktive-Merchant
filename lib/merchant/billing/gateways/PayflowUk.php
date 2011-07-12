<?php

require_once 'Payflow.php';

class Merchant_Billing_PayflowUk extends Merchant_Billing_Payflow
{

    public static $default_currency = 'GBP';
    protected $partner = 'PayPalUk';
    public static $supported_cardtypes = array('visa', 'master', 'american_express', 'discover', 'solo', 'switch');
    public static $supported_countries = array('GB');
    public static $homepage_url = 'https://www.paypal.com/uk/cgi-bin/webscr?cmd=_wp-pro-overview-outside';
    public static $display_name = 'PayPal Payflow Pro (UK)';

}

?>
