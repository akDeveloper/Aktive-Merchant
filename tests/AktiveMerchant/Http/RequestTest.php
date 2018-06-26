<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

namespace AktiveMerchant\Http;

use PHPUnit\Framework\TestCase;
use AktiveMerchant\Http\Request;
use AktiveMerchant\Http\Adapter\cUrl;
use AktiveMerchant\Http\RequestInterface;

class RequestTest extends TestCase
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
