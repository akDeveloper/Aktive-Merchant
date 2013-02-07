<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

require_once '../autoload.php';

use AktiveMerchant\Support\GatewaySupport;

class GatewaySupportTest extends PHPUnit_Framework_TestCase
{
    public function testSupport()
    {
        $s = new GatewaySupport();

        $s->features();
    }
}
