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
            'password' => 'password',
            'inst_id' => '0000'
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

    public function testBuildAuthorisationXML()
    {
        $options = array(
            'order_id' => '1234',
            'address' => array(
                'address1' => '1234 Street',
                'zip' => '98004',
                'state' => 'WA',
                'country' => 'USA'
            )
        );

        $xml = $this->gateway->build_authorization_request(100, $this->creditCard, $options, true);

        $output = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE paymentService
PUBLIC "-//WorldPay//DTD WorldPay PaymentService v1//EN"
       "http://dtd.worldpay.com/paymentService_v1.dtd">
<paymentService merchantCode="login" version="1.4">
    <submit>
        <order orderCode="1234" installationId="0000">
            <description>Purchase</description>
            <amount value="10000" currencyCode="GBP" exponent="2"/>
            <paymentDetails>
                <VISA-SSL>
                    <cardNumber>4111111111111111</cardNumber>
                    <expiryDate>
                        <date month="01" year="2015"/>
                    </expiryDate>
                    <cardHolderName>John Doe</cardHolderName>
                    <cvc>000</cvc>
                    <cardAddress>
                        <address>
                            <street>Street</street>
                            <houseNumber>1234</houseNumber>
                            <postalCode>98004</postalCode>
                            <state>WA</state>
                            <countryCode>USA</countryCode>
                        </address>
                    </cardAddress>
                </VISA-SSL>
            </paymentDetails>
        </order>
    </submit>
</paymentService>
XML;

        $this->assertNotNull($xml->__toString());
    }

    public function testBuildCaptureXML()
    {
        $options = array('order_id' => '1234');

        $xml = $this->gateway->build_capture_request(100, 'R50704213207145707', $options, true);

        $string = $xml->__toString();

        $output = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE paymentService
PUBLIC "-//WorldPay//DTD WorldPay PaymentService v1//EN"
       "http://dtd.worldpay.com/paymentService_v1.dtd">
<paymentService merchantCode="login" version="1.4">
 <modify>
  <orderModification orderCode="R50704213207145707">
   <capture>
    <date dayOfMonth="08" month="04" year="2016"></date>
    <amount value="10000" currencyCode="GBP" exponent="2"></amount>
   </capture>
  </orderModification>
 </modify>
</paymentService>
XML;
        $this->assertNotNull($xml->__toString());
    }
}
