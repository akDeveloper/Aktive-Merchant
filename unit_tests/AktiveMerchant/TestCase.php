<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

namespace AktiveMerchant;

use AktiveMerchant\Billing\CreditCard;

class TestCase extends \PHPUnit_Framework_TestCase
{

    public function getFixtures()
    {
        $ini = parse_ini_file("fixtures.ini", true);
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

    function credit_card($number = '4242424242424242', $options = array()) {
      $defaults = array_merge(array(
        "number" => $number,
        "month" => 9,
        "year" => date("Y") + 1,
        "first_name" => 'Longbob',
        "last_name" => 'Longsen',
        "verification_value" => '123',
        "brand" => 'visa'
      ), $options);

      return new CreditCard($defaults);
    }

    function address($options = array()) {
        return array_merge(array(
            "name" => 'Jim Smith',
            "address1" => '1234 My Street',
            "address2" => 'Apt 1',
            "company" => 'Widgets Inc',
            "city" => 'Ottawa',
            "state" => 'ON',
            "zip" => 'K1C2N6',
            "country" => 'CA',
            "phone" => '(555)555-5555',
            "fax" => '(555)555-6666'
        ), $options);
    }
}
