<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

use AktiveMerchant\Billing\Gateways\Payflow;
use AktiveMerchant\Billing\Base;
use AktiveMerchant\Billing\CreditCard;

class PayflowTest extends \AktiveMerchant\TestCase
{

    public $gateway;
    public $amount;
    public $options;
    public $creditcard;

    protected function setUp()
    {
        Base::mode('test');

        $login_info = $this->getFixtures()->offsetGet('payflow');

        $this->gateway = new Payflow($login_info);

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

    public function testPurchase()
    {
        $this->mock_request($this->successful_purchase_response());
        $resp = $this->gateway->purchase($this->amount, $this->creditcard, $this->options);
        $this->assertTrue($resp->success());
        $this->assertEquals('Approved', $resp->message());
        $this->assertEquals('VTHD55395864', $resp->authorization());
    }

    public function testAuthorization()
    {
        $this->mock_request($this->successful_authorize_response());
        $resp = $this->gateway->authorize($this->amount, $this->creditcard, $this->options);
        $this->assertTrue($resp->success());
    }

    public function testCapture()
    {
        $this->mock_request($this->successful_capture_response());
        $resp = $this->gateway->capture($this->amount, 'auth_id', $this->options);
        $this->assertTrue($resp->success());
    }

    public function testVoid()
    {
        $this->mock_request($this->successful_void_response());
        $resp = $this->gateway->void('auth_id', $this->options);
        $this->assertTrue($resp->success());
    }

    private function successful_purchase_response()
    {
    return '<?xml version="1.0" encoding="UTF-8"?>
<XMLPayResponse xmlns="http://www.paypal.com/XMLPay">
   <ResponseData>
      <Vendor>vendor</Vendor>
      <Partner>partner</Partner>
      <TransactionResults>
         <TransactionResult>
            <Result>0</Result>
            <AVSResult>
              <StreetMatch>Service Not Requested</StreetMatch>
              <ZipMatch>Service Not Requested</ZipMatch>
            </AVSResult>
            <CVResult>Service Not Requested</CVResult>
            <Message>Approved</Message>
            <PNRef>VTHD55395864</PNRef>
            <OrigResult>0</OrigResult>
            <ExtData Name="LASTNAME" Value="Gonzalez"></ExtData>
         </TransactionResult>
      </TransactionResults>
   </ResponseData>
</XMLPayResponse>';
    }

    private function successful_authorize_response()
    {
    return '<?xml version="1.0" encoding="UTF-8"?>
<XMLPayResponse>
   <ResponseData>
      <Vendor>vendor</Vendor>
      <Partner>partner</Partner>
      <TransactionResults>
         <TransactionResult>
           <Result>0</Result>
           <AVSResult>
              <StreetMatch>Service Not Available</StreetMatch>
               <ZipMatch>Service Not Available</ZipMatch>
           </AVSResult>
            <CVResult>Service Not Requested</CVResult>
           <Message>Approved</Message>
            <PNRef>V63A09910356</PNRef>
            <AuthCode>747PNI</AuthCode>
            <HostCode>00</HostCode>
            <OrigResult>0</OrigResult>
         </TransactionResult>
      </TransactionResults>
   </ResponseData>
</XMLPayResponse>';
    }

    private function successful_capture_response()
    {
    return '<?xml version="1.0" encoding="UTF-8"?>
<XMLPayResponse>
  <ResponseData>
      <Vendor>vendor</Vendor>
      <Partner>partner</Partner>
      <TransactionResults>
         <TransactionResult>
            <Result>0</Result>
            <AVSResult>
               <StreetMatch>Service Not Available</StreetMatch>
               <ZipMatch>Service Not Available</ZipMatch>
            </AVSResult>
            <CVResult>Service Not Requested</CVResult>
            <Message>Approved</Message>
            <PNRef>V53A09206640</PNRef>
            <AuthCode>747PNI</AuthCode>
            <HostCode>00</HostCode>
            <OrigResult>0</OrigResult>
         </TransactionResult>
      </TransactionResults>
   </ResponseData>
</XMLPayResponse>';
    }

    private function successful_void_response()
    {
    return '<?xml version="1.0" encoding="UTF-8"?>
<XMLPayResponse>
   <ResponseData>
      <Vendor>vendor</Vendor>
      <Partner>partner</Partner>
      <TransactionResults>
         <TransactionResult>
            <Result>0</Result>
            <AVSResult>
               <StreetMatch>Service Not Requested</StreetMatch>
               <ZipMatch>Service Not Requested</ZipMatch>
            </AVSResult>
            <CVResult>Service Not Requested</CVResult>
            <Message>Approved</Message>
            <PNRef>V54A09206748</PNRef>
            <HostCode>00</HostCode>
            <OrigResult>0</OrigResult>
         </TransactionResult>
      </TransactionResults>
   </ResponseData>
</XMLPayResponse>';
    }
}
