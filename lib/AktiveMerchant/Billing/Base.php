<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

namespace AktiveMerchant\Billing;

use AktiveMerchant\Common\Inflect;

/**
 *
 * @package Aktive-Merchant
 * @author  Andreas Kollaros
 * @license http://www.opensource.org/licenses/mit-license.php
 */
class Base
{

    /**
     * @var string
     */
    const VERSION = "1.1.4";

    /**
     * @var string
     */
    public static $gateway_mode;

    /**
     * @var string
     */
    public static $integration_mode;

    /**
     * @var string live | test
     */
    protected static $mode = 'live';


    public static function mode($mode)
    {
        self::$mode             = $mode;
        self::$gateway_mode     = $mode;
        self::$integration_mode = $mode;
    }

    /**
     * Checks if we are in test mode
     *
     * @returns boolean true if we are ina test mode, false if not.
     */
    public static function is_test()
    {
        return self::$gateway_mode == 'test';
    }

    /**
     * Factory method for gateways.
     *
     * Return the matching gateway for the provider
     * $name must be the name of the gateway class in underscore format
     * for AuthorizeNet gateway will be authorize_net
     *
     * <code>
     *      AktiveMerchant\Billing\Base::gateway('authorize_net');
     * </code>
     *
     * @param  string $name    the underscored name of the gateway.
     * @param  array  $options the options for gateway construct.
     *
     * @return \AktiveMerchant\Billing\Gateway the gateway instance
     */
    public static function gateway($name = null, $options = array())
    {
        $gateway = "\\AktiveMerchant\\Billing\\Gateways\\" . Inflect::camelize($name);

        if (class_exists($gateway)) {
            return new $gateway($options);
        }

        throw new Exception("Unable to load class: {$gateway}.");
    }
}
