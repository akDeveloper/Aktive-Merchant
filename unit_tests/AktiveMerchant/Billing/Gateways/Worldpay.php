<?php

use AktiveMerchant\Billing\Gateways\Worldpay;
use AktiveMerchant\Billing\CreditCard;

class WorldPayTest extends PHPUnit_Framework_TestCase
{
    private $gateway;
    private $creditCard;

    protected function setUp()
    {
        $this->gateway = new Worldpay(array(
            'login' => 'login',
            'password' => 'password'
        ));

        $this->creditCard = new CreditCard(
            array(
                "first_name" => "John",
                "last_name" => "Doe",
                "number" => "4111111111111111",
                "month" => "01",
                "year" => "2015",
                "verification_value" => "000"
            )
        );
    }

    function testBuildAuthorisation()
    {
        $options = array(
            'order_id' => '1234',
            'inst_id' => '00000'
        );

        $xml = $this->gateway->build_authorization_request(100, $this->creditCard, $options);

        $this->assertEquals($xml, "");
    }
}
