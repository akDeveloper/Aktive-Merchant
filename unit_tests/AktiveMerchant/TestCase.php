<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

namespace AktiveMerchant;

class TestCase extends \PHPUnit_Framework_TestCase
{
    protected function assert_success($response)
    {
        $this->assertTrue($response->success());
    }

    protected function assert_failure($response)
    {
        $this->assertFalse($response->success());
    }

    protected function mock_request($answer)
    {

        $this->request = $this->getMock(
            'AktiveMerchant\\Mock\\Request', 
            array('getResponseBody')
        );

        $this->request->expects($this->once())
            ->method('getResponseBody')
            ->will($this->returnValue($answer));

        $this->gateway->setRequest($this->request); 
    }
}
