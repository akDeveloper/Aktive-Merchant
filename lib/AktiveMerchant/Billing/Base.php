<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

namespace AktiveMerchant\Billing;

/**
 * Description of MerchantBase
 *
 * @package Aktive-Merchant
 * @author  Andreas Kollaros
 * @license http://www.opensource.org/licenses/mit-license.php
 */
class Base
{

    public static    $gateway_mode;
    public static    $integration_mode;
    protected static $mode = 'production';

    public static function mode($mode)
    {
        self::$mode = $mode;
        self::$gateway_mode = $mode;
        self::$integration_mode = $mode;
    }

    public static function is_test()
    {
        return self::$gateway_mode == 'test';
    }

    /**
     * Return the matching gateway for the provider
     * $name must be the name of the gateway class in underscore format
     * for AuthorizeNet gateway will be authorize_net
     *
     * AktiveMerchant\Billing\Base::gateway('authorize_net');
     */
    public static function gateway($name=null, $options = array())
    {
        $gateway = "\\Merchant\\Billing\\" . self::camelize($name);

        if (class_exists($gateway))
            return new $gateway($options);

        throw new Exception("Unable to load class: {$gateway}.");
    }

    private static function camelize($string)
    {
        return str_replace(' ', '', ucwords(str_replace('_', ' ', $string)));
    }
        
        

}

?>
