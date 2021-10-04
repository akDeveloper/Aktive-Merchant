<?php

declare(strict_types=1);

namespace AktiveMerchant;

use AktiveMerchant\Http\Request;
use PHPUnit\Framework\TestCase as PHPUnitTestCase;

class TestCase extends PHPUnitTestCase
{
    /**
     * @var Request
     */
    protected $request;

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

        $this->request = $this->getMockBuilder('AktiveMerchant\\Mock\\Request')
            ->setMethods(array('getResponseBody'))
            ->getMock();

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
