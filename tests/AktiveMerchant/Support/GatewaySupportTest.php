<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

use AktiveMerchant\Support\GatewaySupport;

class GatewaySupportTest extends PHPUnit_Framework_TestCase
{
    public function testSupport()
    {
        $s = new GatewaySupport();

        ob_start();
        $s->features();
        $output = ob_get_clean();

        $this->assertTrue($output != null);
    }
}
