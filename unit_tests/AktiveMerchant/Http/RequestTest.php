<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

require_once '../autoload.php';

use AktiveMerchant\Http\Request;
use AktiveMerchant\Http\RequestInterface;
use AktiveMerchant\Http\Adapter\cUrl;

class RequestTest extends PHPUnit_Framework_TestCase
{

    protected $ini;

    public function setUp() 
    {
        $this->ini = parse_ini_file("fixtures.ini", true);
    }

    public function testResquest()
    {
        $url = $this->ini['request']['url'];

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
