<?php
/**
 * Description of PiraeusPaycenterTest
 *
 * Usage:
 *   Navigate, from terminal, to folder where this files is located
 *   run phpunit PiraeusPaycenterTest.php
 *
 * @package Aktive Merchant
 * @author  Andreas Kollaros
 * @license http://www.opensource.org/licenses/mit-license.php
 *
 */
require_once dirname(__FILE__) . '/../../../config.php';

class PiraeusPaycenterTest extends PHPUnit_Framework_TestCase {
  public $gateway;
  public $amount;
  public $options;
  public $creditcard;

  /**
   * Setup
   */
  function setUp() {
    Merchant_Billing_Base::mode('test');

    $options = array(
                  'acquire_id' => 'x',
                  'merchant_id' => 'y',
                  'pos_id' => 'z',
                  'user' => 'b',
                  'password' => 'a',
                  'channel_type' => 'c'
                  );
    $this->gateway = new Merchant_Billing_PiraeusPaycenter($options);

    $this->amount = 100;
    $this->creditcard = new Merchant_Billing_CreditCard( array(
        "first_name" => "John",
        "last_name" => "Doe",
        "number" => "4111111111111111",
        "month" => "01",
        "year" => "2015",
        "verification_value" => "000"
      )
    );
    $this->options = array(
      'order_id' => 'REF' . $this->gateway->generate_unique_id(),
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
  public function testSuccessfulPurchase() {
    $this->gateway->expects('ssl_post', $this->successful_purchase_response() );

    $response = $this->gateway->purchase($this->amount, $this->creditcard, $this->options);
    $this->assert_success($response);
    $this->assertTrue($response->test());
    $this->assertEquals('Approved or completed successfully',$response->message());
  }

  /**
   * Private methods
   */

  private function assert_success($response) {
    $this->assertTrue($response->success());
  }

  private function assert_failure($response) {
    $this->assertFalse($response->success());
  }

  private function successful_purchase_response() {
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
