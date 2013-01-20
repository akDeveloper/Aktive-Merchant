<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

namespace AktiveMerchant;

class TestCase extends \PHPUnit_Framework_TestCase
{

    public function getFixtures()
    {
        $ini = parse_ini_file("fixtures.ini", true);
        return new \ArrayIterator($ini);
    }

    protected function assert_success($response)
    {
        $this->assertTrue($response->success(), "Response should be successful but is not. Message was: {$response->message()}");
    }

    protected function assert_failure($response)
    {
        $this->assertFalse($response->success(), "Response should be failure but is not. Message was: {$response->message()}");
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

    protected function assertImplementation(array $billing_interfaces)
    {
        $this->assertInstanceOf(
            '\\AktiveMerchant\\Billing\\Gateway', 
            $this->gateway
        );
        
        foreach ($billing_interfaces as $b) {
            $this->assertInstanceOf(
                "\\AktiveMerchant\\Billing\\Interfaces\\$b", 
                $this->gateway
            ); 
        }
    }
}
