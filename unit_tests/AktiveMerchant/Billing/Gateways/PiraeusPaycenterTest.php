<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

use AktiveMerchant\Billing\Gateways\PiraeusPaycenter;
use AktiveMerchant\Billing\Base;
use AktiveMerchant\Billing\CreditCard;

require_once 'config.php';

/**
 * Unit test PiraeusPaycenter
 *
 * @package Aktive-Merchant
 * @author  Andreas Kollaros
 * @license http://www.opensource.org/licenses/mit-license.php
 *
 */
class PiraeusPaycenterTest extends AktiveMerchant\TestCase
{

    public $gateway;
    public $amount;
    public $options;
    public $creditcard;

    /**
     * Setup
     */
    function setUp()
    {
        Base::mode('test');

        $options = array(
            'acquire_id' => 'x',
            'merchant_id' => 'y',
            'pos_id' => 'z',
            'user' => 'b',
            'password' => 'a',
            'channel_type' => 'c'
        );
        $this->gateway = new PiraeusPaycenter($options);

        $this->amount = 100;
        $this->creditcard = new CreditCard(array(
            "first_name" => "John",
            "last_name" => "Doe",
            "number" => "4111111111111111",
            "month" => "01",
            "year" => "2015",
            "verification_value" => "000"
        )
    );
        $this->options = array(
            'order_id' => 'REF' . $this->gateway->generateUniqueId(),
            'description' => 'Test Transaction',
            'cavv' => 'xxx',
            'eci_flag' => 'xxx',
            'xid' => 'xxx',
            'enrolled' => 'Y',
            'pares_status' => 'Y',
            'signature_verification' => 'Y',
            'country' => 'US',
            'address' => array(
                'address1' => '1234 Street',
                'zip' => '98004',
                'state' => 'WA'
            )
        );
    }

    /**
     * Tests
     */

    public function testInitialization() 
    {
        $this->assertNotNull($this->gateway);

        $this->assertNotNull($this->creditcard);
        
        $this->assertInstanceOf(
            '\\AktiveMerchant\\Billing\\Gateway', 
            $this->gateway
        );
        
        $this->assertInstanceOf(
            '\\AktiveMerchant\\Billing\\Interfaces\\Charge', 
            $this->gateway
        );
        
        $this->assertInstanceOf(
            '\\AktiveMerchant\\Billing\\Interfaces\\Credit', 
            $this->gateway
        );
    }

    public function testSuccessfulPurchase()
    {
        $this->mock_request($this->successful_purchase_response());

        $response = $this->gateway->purchase(
            $this->amount, 
            $this->creditcard, 
            $this->options
        );
        
        $this->assert_success($response);
        $this->assertTrue($response->test());
        $this->assertEquals('Approved or completed successfully', $response->message());
    }

    private function successful_purchase_response()
    {
        return <<<XML
<?xml version="1.0" encoding="utf-8"?>
<soap:Envelope xmlns:soap="http://www.w3.org/2003/05/soap-envelope" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema">
  <soap:Body>
    <ProcessTransactionResponse xmlns="http://piraeusbank.gr/paycenter">
      <TransactionResponse>
        <Header xmlns="http://piraeusbank.gr/paycenter/1.0">
          <RequestType>SALE</RequestType>
          <ResultCode>0</ResultCode>
          <ResultDescription />
          <SupportReferenceID>11530698</SupportReferenceID>
        </Header>
        <Body xmlns="http://piraeusbank.gr/paycenter/1.0">
          <TransactionInfo>
            <StatusFlag>Success</StatusFlag>
            <ResponseCode>00</ResponseCode>
            <ResponseDescription>Approved or completed successfully</ResponseDescription>
            <TransactionID>13092167</TransactionID>
            <TransactionDateTime>2010-07-19T13:25:52</TransactionDateTime>
            <TransactionTraceNum>2</TransactionTraceNum>
            <MerchantReference>165766</MerchantReference>
            <ApprovalCode>032963</ApprovalCode>
            <RetrievalRef>032963032963</RetrievalRef>
            <PackageNo>6</PackageNo>
            <SessionKey xsi:nil="true" />
          </TransactionInfo>
        </Body>
      </TransactionResponse>
    </ProcessTransactionResponse>
  </soap:Body>
</soap:Envelope>
XML;
        return $xml;
    }

}

?>
