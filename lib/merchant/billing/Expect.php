<?php

/**
 * Description of Gateway
 *
 * This class allows modify the return value of ssl_get and ssl_post method of
 * a gateway.
 *
 * Used for unit testing purpose only, when we want to test a gateway response
 * without actually make a request to payment server.
 *
 * Usage:
 * $gateway->expects(<method>, <response_value> );
 * When we call $gateway->method will get the <response_value>
 * <method> can be ssl_post or ssl_get
 *
 *
 * @package Aktive Merchant
 * @author  Andreas Kollaros
 * @license http://www.opensource.org/licenses/mit-license.php
 */
class Merchant_Billing_Expect
{

    protected $expects = array();

    public function expects($method, $return_value)
    {
        $this->expects[$method] = $return_value;
    }

}

?>
