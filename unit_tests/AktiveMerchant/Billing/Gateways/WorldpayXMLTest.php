<?php

use AktiveMerchant\Billing\Gateways\Worldpay;
use AktiveMerchant\Billing\CreditCard;

class WorldPayXMLTest extends PHPUnit_Framework_TestCase
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

    function testBuildAuthorisationXML()
    {
        $options = array(
            'order_id' => '1234',
            'inst_id' => '00000',
            'address' => array(
                'address1' => '1234 Street',
                'zip' => '98004',
                'state' => 'WA',
                'country' => 'USA'
            )
        );

        $xml = $this->gateway->build_authorization_request(100, $this->creditCard, $options, true);

        echo $xml;

        $this->assertNotNull($xml);
    }

    function testBuildCaptureXML()
    {
        $options = array('order_id' => '1234');

        $xml = $this->gateway->build_capture_request(100, 'R50704213207145707', $options, true);

        // echo $xml;

        $this->assertNotNull($xml);
    }
}
