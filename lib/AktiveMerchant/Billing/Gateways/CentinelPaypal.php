<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

namespace AktiveMerchant\Billing\Gateways;

use AktiveMerchant\Billing\Gateways\Centinel;

/**
 * Extension of Centinel gateway that connects to the
 * paypal subdomain of Cardinal Commmerce in live env
 *
 * @license http://www.opensource.org/licenses/mit-license.php
 */
class CentinelPaypal extends Centinel
{
    const LIVE_URL = 'https://paypal.cardinalcommerce.com/maps/txns.asp';

    public static $display_name = 'Centinel 3D Secure for Paypal';

    protected function commit($action, $money, $parameters)
    {
        $this->getAdapter()->setOption(CURLOPT_SSLVERSION, 3);

        return parent::commit($action, $money, $parameters);
    }
}
