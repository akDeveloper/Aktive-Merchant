<?php

namespace AktiveMerchant\Billing\Gateways;

use namespace AktiveMerchant\Billing\Gateways\Centinel;

/**
 * Extension of Centinel gateway that connects to the
 * paypal subdomain of Cardinal Commmerce in live env
 *
 * @package Aktive-Merchant
 * @license http://www.opensource.org/licenses/mit-license.php
 */
class CentinelPaypal extends Centinel 
{
  protected $live_url = 'https://paypal.cardinalcommerce.com/maps/txns.asp';
}

?>