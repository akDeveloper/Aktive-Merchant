<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

use AktiveMerchant\Billing\Gateways\PiraeusPaycenter;
use AktiveMerchant\Billing\Base;
use AktiveMerchant\Billing\CreditCard;

/**
 * Unit test PiraeusPaycenter
 *
 * @package Aktive-Merchant
 * @author  Andreas Kollaros
 * @license http://www.opensource.org/licenses/mit-license.php
 *
 */
class PiraeusPaycenterTest extends \AktiveMerchant\TestCase
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


        $options = $this->getFixtures()->offsetGet('piraeus_paycenter');

        $this->gateway = new PiraeusPaycenter($options);

        $this->amount = 1;
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

        $this->assertImplementation(
            array(
                'Charge',
                'Credit'
            )
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

    public function testCase01VisaPurchase()
    {
        $this->mock_request($this->successful_test_case_01_visa_purchase_response());

        $response = $this->gateway->purchase(
            $this->amount,
            $this->creditcard,
            $this->options
        );

        $this->assert_success($response);
        $this->assertTrue($response->test());
        $this->assertEquals('0', $response->result_code);
        $this->assertEquals('00', $response->response_code);
        $this->assertEquals('Approved or completed successfully', $response->message());
    }

    public function testCase02VisaPurchase()
    {
        $this->mock_request($this->successful_test_case_02_visa_purchase_response());

        $response = $this->gateway->purchase(
            $this->amount,
            $this->creditcard,
            $this->options
        );

        $this->assert_failure($response);
        $this->assertTrue($response->test());
        $this->assertEquals('0', $response->result_code);
        $this->assertEquals('12', $response->response_code);
        $this->assertEquals('Declined', $response->message());
    }

    private function successful_test_case_01_visa_purchase_response()
    {
        return <<<XML
<?xml version="1.0"?>
<soap:Envelope xmlns:soap="http://www.w3.org/2003/05/soap-envelope" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema">
<soap:Body>
  <ProcessTransactionResponse xmlns="http://piraeusbank.gr/paycenter">
    <TransactionResponse>
      <Header xmlns="http://piraeusbank.gr/paycenter/1.0">
        <RequestType>SALE</RequestType>
        <MerchantInfo>
          <MerchantID>XXXXXXXXXX</MerchantID>
          <PosID>XXXXXXXXXX</PosID>
          <ChannelType>3DSecure</ChannelType>
          <User>XXxxxxxx</User>
        </MerchantInfo>
        <ResultCode>0</ResultCode>
        <ResultDescription>No Error</ResultDescription>
        <SupportReferenceID>39668243</SupportReferenceID>
      </Header>
      <Body xmlns="http://piraeusbank.gr/paycenter/1.0">
        <TransactionInfo>
          <StatusFlag>Success</StatusFlag>
          <ResponseCode>00</ResponseCode>
          <ResponseDescription>Approved or completed successfully</ResponseDescription>
          <TransactionID>31900858</TransactionID>
          <TransactionDateTime>2014-11-21T14:29:37</TransactionDateTime>
          <TransactionTraceNum>27</TransactionTraceNum>
          <MerchantReference>REF2125019069</MerchantReference>
          <ApprovalCode>626855</ApprovalCode>
          <RetrievalRef>626855626855</RetrievalRef>
          <PackageNo>2</PackageNo>
          <SessionKey xsi:nil="true"/>
        </TransactionInfo>
      </Body>
    </TransactionResponse>
  </ProcessTransactionResponse>
</soap:Body>
</soap:Envelope>
XML;
    }

    private function successful_test_case_02_visa_purchase_response()
    {
        return <<<XML
<?xml version="1.0"?>
<soap:Envelope xmlns:soap="http://www.w3.org/2003/05/soap-envelope" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema">
<soap:Body>
  <ProcessTransactionResponse xmlns="http://piraeusbank.gr/paycenter">
    <TransactionResponse>
      <Header xmlns="http://piraeusbank.gr/paycenter/1.0">
        <RequestType>SALE</RequestType>
        <MerchantInfo>
          <MerchantID>XXXXXXXXXX</MerchantID>
          <PosID>XXXXXXXXXX</PosID>
          <ChannelType>3DSecure</ChannelType>
          <User>XXxxxxxx</User>
        </MerchantInfo>
        <ResultCode>0</ResultCode>
        <ResultDescription>No Error</ResultDescription>
        <SupportReferenceID>39665444</SupportReferenceID>
      </Header>
      <Body xmlns="http://piraeusbank.gr/paycenter/1.0">
        <TransactionInfo>
          <StatusFlag>Failure</StatusFlag>
          <ResponseCode>12</ResponseCode>
          <ResponseDescription>Declined</ResponseDescription>
          <TransactionID>31898663</TransactionID>
          <TransactionDateTime>2014-11-21T12:54:00</TransactionDateTime>
          <TransactionTraceNum>7</TransactionTraceNum>
          <MerchantReference>REF1824004080</MerchantReference>
          <ApprovalCode xsi:nil="true"/>
          <RetrievalRef>702256702256</RetrievalRef>
          <PackageNo>2</PackageNo>
          <SessionKey xsi:nil="true"/>
        </TransactionInfo>
      </Body>
    </TransactionResponse>
  </ProcessTransactionResponse>
</soap:Body>
</soap:Envelope>
XML;
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
