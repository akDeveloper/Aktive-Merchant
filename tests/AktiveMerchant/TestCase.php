<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

namespace AktiveMerchant;

class TestCase extends \PHPUnit_Framework_TestCase
{
    public function getFixtures()
    {
        $ini = parse_ini_file(__DIR__ . "/../fixtures.ini", true);
        return new \ArrayIterator($ini);
    }

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

        $this->request->method('getResponseBody')
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
