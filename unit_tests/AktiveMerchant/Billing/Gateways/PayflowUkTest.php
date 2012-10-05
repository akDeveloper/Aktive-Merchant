<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

require_once'config.php';

use AktiveMerchant\Billing\Gateways\PayflowUk;
use AktiveMerchant\Billing\Base;
use AktiveMerchant\Billing\CreditCard;

class PayflowUkTest extends AktiveMerchant\TestCase
{

    public $gateway;
    public $amount;
    public $options;
    public $creditcard;

    protected function setUp()
    {
        Base::mode('test');

        $login_info = $this->getFixtures()->offsetGet('payflowuk');
        
        $this->gateway = new PayflowUk($login_info);

        $this->amount = 100.00;

        $this->creditcard = new CreditCard(array(
            'number' => '5105105105105100',
            'month' => 11,
            'year' => 2009,
            'first_name' => 'Cody',
            'last_name' => 'Fauser',
            'verification_value' => '000',
            'type' => 'master'
        ));

        $this->options = array(
            'billing_address' => array(
                'name' => 'Cody Fauser',
                'address1' => '1234 Shady Brook Lane',
                'city' => 'Ottawa',
                'state' => 'ON',
                'country' => 'CA',
                'zip' => '90210',
                'phone' => '555-555-5555'
            ),
            'email' => 'cody@example.com'
        );
    }

    public function testInitialization()
    {
        $this->assertNotNull($this->gateway);
        $this->assertNotNull($this->creditcard);
    }

    function testAuthorizationAndCapture()
    {
        $this->mock_request($this->successful_authorize_response());
        
        $auth = $this->gateway->authorize(
            $this->amount, 
            $this->creditcard, 
            $this->options
        );
        
        $this->assertTrue($auth->success());
        $this->assertEquals('Approved', $auth->message());
        
        //$capture = $this->gateway->capture($this->amount, $auth->authorization(), $this->options);
        //$this->assertTrue($capture->success());
    }

    private function successful_authorize_response()
    {
    return '<?xml version="1.0" encoding="UTF-8"?>
    <ResponseData>
    <Result>0</Result>
    <Message>Approved</Message>
    <Partner>verisign</Partner>
    <HostCode>000</HostCode>
    <ResponseText>AP</ResponseText>
    <PnRef>VUJN1A6E11D9</PnRef>
    <IavsResult>N</IavsResult>
    <ZipMatch>Match</ZipMatch>
    <AuthCode>094016</AuthCode>
    <Vendor>ActiveMerchant</Vendor>
    <AvsResult>Y</AvsResult>
    <StreetMatch>Match</StreetMatch>
    <CvResult>Match</CvResult>
    </ResponseData>';
    }

}
