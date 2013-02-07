<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

namespace AktiveMerchant\Billing\Gateways;

use AktiveMerchant\Billing\Gateways\Centinel;

/**
 * Extension of Centinel gateway that connects to the
 * paypal subdomain of Cardinal Commmerce in live env
 *
 * @package Aktive-Merchant
 * @license http://www.opensource.org/licenses/mit-license.php
 */
class CentinelPaypal extends Centinel 
{
    const LIVE_URL = 'https://paypal.cardinalcommerce.com/maps/txns.asp';
    
    public static $display_name = 'Centinel 3D Secure for Paypal';
}
