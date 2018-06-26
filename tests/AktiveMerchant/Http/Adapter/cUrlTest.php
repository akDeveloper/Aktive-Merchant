<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

namespace AktiveMerchant\Http\Adapter;

use PHPUnit\Framework\TestCase;
use AktiveMerchant\Mock\Request;

class cUrlTest extends TestCase
{
    public function testAdapterConfig()
    {
        $request = new Request();
        $request->setUrl('http://www.httpbin.org/get');

        $adapter = new cUrl();

        $adapter->setOption('connect_timeout', 20);

        $this->assertEquals(20, $adapter->getOption('connect_timeout'));
        $this->assertEquals(20, $adapter->getOption(CURLOPT_CONNECTTIMEOUT));

        $adapter->sendRequest($request);

        $options = $adapter->getOptions();

        $this->assertEquals(20, $options[CURLOPT_CONNECTTIMEOUT]);
    }
}
