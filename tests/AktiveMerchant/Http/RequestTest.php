<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

namespace AktiveMerchant\Http;

use AktiveMerchant\Http\Request;
use AktiveMerchant\Http\RequestInterface;
use AktiveMerchant\Http\Adapter\cUrl;

class RequestTest extends \PHPUnit_Framework_TestCase
{
    public function testResquest()
    {
        $url = 'http://www.httpbin.org/get';

        $request = new Request($url, RequestInterface::METHOD_GET);
        $request->setBody(array('test'=>1, 'var2'=>'lorem'));
        $request->addHeader('Content-Type', 'text/xml');

        $adapter = $request->getAdapter();

        $this->assertInstanceOf(
            "\\AktiveMerchant\\Http\\Adapter\\cUrl",
            $adapter
        );

        $adapter->sendRequest($request);

        $adapter->getInfo();
    }
}
