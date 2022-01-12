<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

namespace AktiveMerchant\Http\Adapter;

use AktiveMerchant\Mock\Request;
use PHPUnit_Framework_TestCase;

class cUrlTest extends PHPUnit_Framework_TestCase
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

    /**
     * @throws Exception
     */
    public function testItShouldOverrideOptionsOnConcurrentRequests()
    {
        $request = new Request();
        $adapter = new cUrl();

        $request->setUrl('http://www.httpbin.org/get');
        $adapter->sendRequest($request);

        $concurrentRequestUrl = 'https://www.examples.com/';
        $request->setUrl($concurrentRequestUrl);
        $adapter->sendRequest($request);
        $concurrentOptions = $adapter->getOptions();

        $this->assertEquals(
            $concurrentRequestUrl,
            $concurrentOptions[10002]
        );
    }
}
