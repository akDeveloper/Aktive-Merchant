<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

use AktiveMerchant\Billing\Gateways\WorldPay;
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
require_once'config.php';

class WorldpayTest extends AktiveMerchant\TestCase
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
        $this->mock_request($this->successful_authorize_response());
        $resp = $this->gateway->authorize($this->amount, $this->creditcard, $this->options);
        $this->assertTrue($resp->success());
        $this->assertEquals($resp->authorization(), 'R50704213207145707');
    }

    public function testFailedAuthorization()
    {
        $this->mock_request($this->failed_authorize_response());
        $resp = $this->gateway->authorize($this->amount, $this->creditcard, $this->options);
        $this->assertFalse($resp->success());
        $this->assertEquals($resp->message(), 'Invalid payment details : Card number : 4111********1111');
    }

    public function testCapture()
    {
        $this->mock_request($this->successful_capture_response());
        $resp = $this->gateway->capture($this->amount, 'R50704213207145707', $this->options);
        $this->assertTrue($resp->success());
    }
    
    private function successful_authorize_response()
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

    private function failed_authorize_response()
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

    private function successful_capture_response()
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
