<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

namespace AktiveMerchant\Billing\Gateways;

use AktiveMerchant\TestCase;
use AktiveMerchant\Billing\Base;
use AktiveMerchant\Billing\CreditCard;

/**
 * Description of WorldPayTest
 *
 * @package Aktive-Merchant
 * @author  Andreas Kollaros
 * @license http://www.opensource.org/licenses/mit-license.php MIT License
 *
 */
class WorldpayTest extends TestCase
{
    public $gateway;
    public $amount;
    public $options;
    public $creditcard;

    protected function setUp()
    {
        Base::mode('test');

        $login_info = $this->getFixtures()->offsetGet('worldpay');

        $this->gateway = new Worldpay($login_info);

        $this->amount = 100.00;

        $this->creditcard = new CreditCard(array(
            'number' => '5105105105105100',
            'month' => 11,
            'year' => 2009,
            'first_name' => 'John',
            'last_name' => 'Doe',
            'verification_value' => '000',
            'type' => 'master'
        ));

        $this->options = array(
            'order_id' => '1234',
            'billing_address' => array(
                'name' => 'John Doe',
                'address1' => '1234 my address',
                'city' => 'Neverland',
                'state' => 'ON',
                'country' => 'CA',
                'zip' => '90210',
                'phone' => '555-555-5555'
            ),
            'email' => 'john@example.com'
        );
    }

    public function testInitialization()
    {
        $this->assertNotNull($this->gateway);
        $this->assertNotNull($this->creditcard);
    }

    public function testSuccessfulAuthorization()
    {
        $this->mock_request($this->successfulAuthorizeResponse());
        $resp = $this->gateway->authorize($this->amount, $this->creditcard, $this->options);
        $this->assertTrue($resp->success());
        $this->assertEquals($resp->authorization(), 'R50704213207145707');

        $this->assertEquals($this->successfulAuthorizeRequest(), $this->request->getBody());
    }

    public function testFailedAuthorization()
    {
        $this->mock_request($this->failedAuthorizeResponse());
        $resp = $this->gateway->authorize($this->amount, $this->creditcard, $this->options);
        $this->assertFalse($resp->success());
        $this->assertEquals($resp->message(), 'Invalid payment details : Card number : 4111********1111');
    }

    public function testSuccessfulCapture()
    {
        $this->mock_request($this->successfulCaptureResponse());
        $resp = $this->gateway->capture($this->amount, 'R50704213207145707', $this->options);
        $this->assertTrue($resp->success());
        $this->assertEquals($this->successfulCaptureRequest(), $this->request->getBody());
    }

    private function successfulAuthorizeRequest()
    {
        return '<?xml version="1.0" encoding="UTF-8"?><!DOCTYPE paymentService PUBLIC "-//WorldPay//DTD WorldPay PaymentService v1//EN" "http://dtd.worldpay.com/paymentService_v1.dtd"><paymentService merchantCode="x" version="1.4"><submit><order orderCode="1234" installationId="inst_id"><description>Purchase</description><amount value="10000" currencyCode="GBP" exponent="2"/><paymentDetails><ECMC-SSL><cardNumber>5105105105105100</cardNumber><expiryDate><date month="11" year="2009"/></expiryDate><cardHolderName>John Doe</cardHolderName><cvc>000</cvc><cardAddress><address><firstName>John</firstName><lastName>Doe</lastName><street>my address</street><houseNumber>1234</houseNumber><postalCode>90210</postalCode><city>Neverland</city><state>ON</state><countryCode>CA</countryCode><telephoneNumber>555-555-5555</telephoneNumber></address></cardAddress></ECMC-SSL></paymentDetails></order></submit></paymentService>';
    }

    private function successfulCaptureRequest()
    {

        $now = new \DateTime(null, new \DateTimeZone('UTC'));
        $dom = $now->format('d');
        $m = $now->format('m');
        $y = $now->format('Y');
        return '<?xml version="1.0" encoding="UTF-8"?><!DOCTYPE paymentService PUBLIC "-//WorldPay//DTD WorldPay PaymentService v1//EN" "http://dtd.worldpay.com/paymentService_v1.dtd"><paymentService merchantCode="x" version="1.4"><modify><orderModification orderCode="R50704213207145707"><capture><date dayOfMonth="'.$dom.'" month="'.$m.'" year="'.$y.'"/><amount value="10000" currencyCode="GBP" exponent="2"/></capture></orderModification></modify></paymentService>';
    }

    private function successfulAuthorizeResponse()
    {
        return '<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE paymentService PUBLIC "-//Bibit//DTD Bibit PaymentService v1//EN" "http://dtd.bibit.com/paymentService_v1.dtd">
<paymentService version="1.4" merchantCode="XXXXXXXXXXXXXXX">
<reply>
  <orderStatus orderCode="R50704213207145707">
    <payment>
      <paymentMethod>VISA-SSL</paymentMethod>
      <amount value="15000" currencyCode="HKD" exponent="2" debitCreditIndicator="credit"/>
      <lastEvent>AUTHORISED</lastEvent>
      <CVCResultCode description="UNKNOWN"/>
      <AVSResultCode description="UNKNOWN"/>
      <balance accountType="IN_PROCESS_AUTHORISED">
        <amount value="15000" currencyCode="HKD" exponent="2" debitCreditIndicator="credit"/>
      </balance>
      <cardNumber>4111********1111</cardNumber>
      <riskScore value="1"/>
    </payment>
  </orderStatus>
</reply>
</paymentService>';
    }

    private function failedAuthorizeResponse()
    {
        return '<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE paymentService PUBLIC "-//Bibit//DTD Bibit PaymentService v1//EN" "http://dtd.bibit.com/paymentService_v1.dtd">
<paymentService version="1.4" merchantCode="XXXXXXXXXXXXXXX">
<reply>
    <error code="7">
      <![CDATA[Invalid payment details : Card number : 4111********1111]]>
    </error>
</reply>
</paymentService>';
    }

    private function successfulCaptureResponse()
    {
        return '<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE paymentService PUBLIC "-//Bibit//DTD Bibit PaymentService v1//EN" "http://dtd.bibit.com/paymentService_v1.dtd">
<paymentService version="1.4" merchantCode="SPREEDLY">
<reply>
  <ok>
    <captureReceived orderCode="33955f6bb4524813b51836de76228983">
      <amount value="100" currencyCode="GBP" exponent="2" debitCreditIndicator="credit"/>
    </captureReceived>
  </ok>
</reply>
</paymentService>';
    }
}
