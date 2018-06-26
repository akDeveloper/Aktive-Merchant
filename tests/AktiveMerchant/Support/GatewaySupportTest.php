<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

use PHPUnit\Framework\TestCase;
use AktiveMerchant\Support\GatewaySupport;

class GatewaySupportTest extends TestCase
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
