<?php

use AktiveMerchant\Billing\Gateways\SecurePayAu;
use AktiveMerchant\Billing\Base;
use AktiveMerchant\Billing\CreditCard;

require_once 'config.php';

class SecurePayAuTest extends \AktiveMerchant\TestCase {
  public $gateway;
  public $credit_card;
  public $amount;
  public $options;

  function setup() {
    $this->gateway = new SecurePayAu(array(
                 'login' => 'login',
                 'password' => 'password'
   ));

    $this->credit_card = $this->credit_card();
    $this->amount = 100;

    $this->options = array(
      'order_id' => '1',
      'billing_address' => $this->address(),
      'description' => 'Store Purchase'
    );
  }


  function credit_card($number = '4242424242424242', $options = array()) {
    $year = new DateTime("+ 1 year");
    $year = $year->format("Y");
      $defaults = $options + array(
        'number' => $number,
        'month' => 9,
        'year' => $year,
        'first_name' => 'Longbob',
        'last_name' => 'Longsen',
        'verification_value' => '123',
        'brand' => 'visa'
      );
      return new CreditCard($defaults);
    }

      function address($options = array())
      {
      return $options + array(
        'name' => 'Jim Smith',
        'address1' => '1234 My Street',
        'address2' => 'Apt 1',
        'company' => 'Widgets Inc',
        'city' => 'Ottawa',
        'state' => 'ON',
        'zip' => 'K1C2N6',
        'country' => 'CA',
        'phone' => '(555)555-5555',
        'fax' => '(555)555-6666'
      );
    }

  function test_supported_countries() {
    $this->assertEquals(array('AU'), SecurePayAu::$supported_countries);
  }

  function test_supported_card_types() {
    $this->assertEquals(array('visa', 'master', 'american_express', 'diners_club', 'jcb'), SecurePayAu::$supported_cardtypes);
  }


  function test_successful_purchase_with_live_data() {

    $this->mock_request($this->successful_live_purchase_response());

    $response = $this->gateway->purchase($this->amount, $this->credit_card, $this->options);
    $this->assertInstanceOf('\AktiveMerchant\Billing\Response', $response);
    $this->assertTrue($response->success());
    $this->assertEquals('000000*#1047.5**211700', $response->authorization());

  }

  
  function successful_live_purchase_response() {

    return <<<XML
    <?xml version="1.0" encoding="UTF-8"?>
    <SecurePayMessage>
      <MessageInfo>
        <messageID>8af793f9af34bea0cf40f5fb5c630c</messageID>
        <messageTimestamp>20080802041625665000+660</messageTimestamp>
        <apiVersion>xml-4.2</apiVersion>
      </MessageInfo>
      <RequestType>Payment</RequestType>
      <MerchantInfo>
        <merchantID>XYZ0001</merchantID>
      </MerchantInfo>
      <Status>
        <statusCode>000</statusCode>
        <statusDescription>Normal</statusDescription>
      </Status>
      <Payment>
        <TxnList count="1">
          <Txn ID="1">
            <txnType>0</txnType>
            <txnSource>23</txnSource>
            <amount>211700</amount>
            <currency>AUD</currency>
            <purchaseOrderNo>#1047.5</purchaseOrderNo>
            <approved>Yes</approved>
            <responseCode>77</responseCode>
            <responseText>Approved</responseText>
            <thinlinkResponseCode>100</thinlinkResponseCode>
            <thinlinkResponseText>000</thinlinkResponseText>
            <thinlinkEventStatusCode>000</thinlinkEventStatusCode>
            <thinlinkEventStatusText>Normal</thinlinkEventStatusText>
            <settlementDate>20080525</settlementDate>
            <txnID>000000</txnID>
            <CreditCardInfo>
              <pan>424242...242</pan>
              <expiryDate>07/11</expiryDate>
              <cardType>6</cardType>
              <cardDescription>Visa</cardDescription>
            </CreditCardInfo>
          </Txn>
        </TxnList>
      </Payment>
    </SecurePayMessage>
XML;
  }


  function successful_authorization_response() {
    return <<<XML
      <?xml version="1.0" encoding="UTF-8" standalone="no"?><SecurePayMessage><MessageInfo><messageID>18071a6170073a7ef231ef048217be</messageID><messageTimestamp>20102807071229455000+600</messageTimestamp><apiVersion>xml-4.2</apiVersion></MessageInfo><RequestType>Payment</RequestType><MerchantInfo><merchantID>CAX0001</merchantID></MerchantInfo><Status><statusCode>000</statusCode><statusDescription>Normal</statusDescription></Status><Payment><TxnList count="1"><Txn ID="1"><txnType>10</txnType><txnSource>23</txnSource><amount>100</amount><currency>AUD</currency><purchaseOrderNo>1</purchaseOrderNo><approved>Yes</approved><responseCode>00</responseCode><responseText>Approved</responseText><thinlinkResponseCode>100</thinlinkResponseCode><thinlinkResponseText>000</thinlinkResponseText><thinlinkEventStatusCode>000</thinlinkEventStatusCode><thinlinkEventStatusText>Normal</thinlinkEventStatusText><settlementDate>20100728</settlementDate><txnID>269057</txnID><preauthID>369057</preauthID><CreditCardInfo><pan>444433...111</pan><expiryDate>09/11</expiryDate><cardType>6</cardType><cardDescription>Visa</cardDescription></CreditCardInfo></Txn></TxnList></Payment></SecurePayMessage>
XML;
  }
/* can't really do this that easily.
  function test_purchase_with_creditcard_calls_commit_with_purchase() {
    $this->gateway.expects(:commit).with(:purchase, anything)

    $this->gateway.purchase($this->amount, $this->credit_card, $this->options)
  }
*/

  function test_successful_authorization() {
    $this->mock_request($this->successful_authorization_response());

    $this->assertNotNull($response = $this->gateway->authorize($this->amount, $this->credit_card, $this->options));
    $this->assert_success($response);
    $this->assertEquals('269057*1*369057*100', $response->authorization());
  }

  function test_failed_authorization() {
    $this->mock_request($this->failed_authorization_response());

    $this->assertNotNull($response = $this->gateway->authorize($this->amount, $this->credit_card, $this->options));
    $this->assert_failure($response);
    $this->assertEquals("Insufficient Funds", $response->message());
  }

  function test_successful_capture() {
    $this->mock_request($this->successful_capture_response());

    $this->assertNotNull($response = $this->gateway->capture($this->amount, "crazy*reference*thingy*100", array()));
    $this->assert_success($response);
    $this->assertEquals("Approved", $response->message());
  }

  function test_failed_capture() {
    $this->mock_request($this->failed_capture_response());

    $this->assertNotNull($response = $this->gateway->capture($this->amount, "crazy*reference*thingy*100"));
    $this->assert_failure($response);
    $this->assertEquals("Preauth was done for smaller amount", $response->message());
  }

  function test_successful_refund() {
    $this->mock_request($this->successful_refund_response());
    $this->assert_success($this->gateway->refund($this->amount, "crazy*reference*thingy*100", array()));
  }

  function test_failed_refund() {
    $this->mock_request($this->failed_refund_response());

    $this->assertNotNull($response = $this->gateway->refund($this->amount, "crazy*reference*thingy*100"));
    $this->assert_failure($response);
    $this->assertEquals("Only $1.00 available for refund", $response->message());
  }

/*
  function test_deprecated_credit() {
    $this->mock_request($this->successful_refund_response());

    $this->assert_deprecation_warning(Gateway::CREDIT_DEPRECATION_MESSAGE,($this->gateway) do);
      $this->assert_success($this->gateway->credit($this->amount, "crazy*reference*thingy*100", array()));
    }
  }
*/

  function test_successful_void() {
    $this->mock_request($this->successful_void_response());

    $this->assertNotNull($response = $this->gateway->void("crazy*reference*thingy*100", array()));
    $this->assert_success($response);
  }

  function test_failed_void() {
    $this->mock_request($this->failed_void_response());

    $this->assertNotNull($response = $this->gateway->void("crazy*reference*thingy*100"));
    $this->assert_failure($response);
    $this->assertEquals("Transaction was done for different amount", $response->message());
  }

  function test_failed_login() {
    $this->mock_request($this->failed_login_response());

    $this->assertNotNull($response = $this->gateway->purchase($this->amount, $this->credit_card, $this->options));
    $this->assertInstanceOf('\AktiveMerchant\Billing\Response', $response);
    $this->assert_failure($response);
    $this->assertEquals("Invalid merchant ID", $response->message());
  }

  function test_successful_store() {
    $this->mock_request($this->successful_store_response());

    $this->assertNotNull($response = $this->gateway->store($this->credit_card, array('billing_id' => 'test3', 'amount' => 123)));
    $this->assertInstanceOf('\AktiveMerchant\Billing\Response', $response);
    $this->assertEquals("Successful", $response->message());
    $params = $response->params();
    $this->assertEquals('test3', $params['client_id']);
  }

  function test_successful_unstore() {
    $this->mock_request($this->successful_unstore_response());

    $this->assertNotNull($response = $this->gateway->unstore('test2'));
    $this->assertInstanceOf('\AktiveMerchant\Billing\Response', $response);
    $this->assertEquals("Successful", $response->message());
    $params = $response->params();
    $this->assertEquals('test2', $params['client_id']);
  }

  function test_successful_triggered_payment() {
    $this->mock_request($this->successful_triggered_payment_response());

    $this->assertNotNull($response = $this->gateway->purchase($this->amount, 'test3', $this->options));
    $this->assertInstanceOf('\AktiveMerchant\Billing\Response', $response);
    $this->assertEquals("Approved", $response->message());
    $this->assertEquals('test3', $response->params['client_id']);
  }

  private

  function successful_store_response() {
    return <<<XML
    <?xml version="1.0" encoding="UTF-8"?>
    <SecurePayMessage>
      <MessageInfo>
        <messageID>8af793f9af34bea0ecd7eff71b37ef</messageID>
        <messageTimestamp>20040710144410220000+600</messageTimestamp>
        <apiVersion>spxml-3.0</apiVersion>
      </MessageInfo>
      <RequestType>Periodic</RequestType>
      <MerchantInfo>
        <merchantID>ABC0001</merchantID>
      </MerchantInfo>
      <Status>
        <statusCode>0</statusCode>
        <statusDescription>Normal</statusDescription>
      </Status>
      <Periodic>
        <PeriodicList count="1">
          <PeriodicItem ID="1">
            <actionType>add</actionType>
            <clientID>test3</clientID>
            <responseCode>00</responseCode>
            <responseText>Successful</responseText>
            <successful>yes</successful>
            <CreditCardInfo>
              <pan>444433...111</pan>
              <expiryDate>09/15</expiryDate>
              <recurringFlag>no</recurringFlag>
            </CreditCardInfo>
            <amount>1100</amount>
            <periodicType>4</periodicType>
          </PeriodicItem>
        </PeriodicList>
      </Periodic>
    </SecurePayMessage>
XML;
  }

  function successful_unstore_response() {
    return <<<XML
    <?xml version="1.0" encoding="UTF-8"?>
    <SecurePayMessage>
      <MessageInfo>
        <messageID>8af793f9af34bea0ecd7eff71c3ef1</messageID>
        <messageTimestamp>20040710150207549000+600</messageTimestamp>
        <apiVersion>spxml-3.0</apiVersion>
      </MessageInfo>
      <RequestType>Periodic</RequestType>
      <MerchantInfo>
        <merchantID>ABC0001</merchantID>
      </MerchantInfo>
      <Status>
        <statusCode>0</statusCode>
        <statusDescription>Normal</statusDescription>
      </Status>
      <Periodic>
        <PeriodicList count="1">
          <PeriodicItem ID="1">
            <actionType>delete</actionType>
            <clientID>test2</clientID>
            <responseCode>00</responseCode>
            <responseText>Successful</responseText>
            <successful>yes</successful>
          </PeriodicItem>
        </PeriodicList>
      </Periodic>
    </SecurePayMessage>
XML;
  }

  function successful_triggered_payment_response() {
    return <<<XML
    <?xml version="1.0" encoding="UTF-8"?>
    <SecurePayMessage>
      <MessageInfo>
        <messageID>8af793f9af34bea0ecd7eff71c94d6</messageID>
        <messageTimestamp>20040710150808428000+600</messageTimestamp>
        <apiVersion>spxml-3.0</apiVersion>
      </MessageInfo>
      <RequestType>Periodic</RequestType>
      <MerchantInfo>
        <merchantID>ABC0001</merchantID>
      </MerchantInfo>
      <Status>
        <statusCode>0</statusCode>
        <statusDescription>Normal</statusDescription>
      </Status>
      <Periodic>
        <PeriodicList count="1">
          <PeriodicItem ID="1">
            <actionType>trigger</actionType>
            <clientID>test3</clientID>
            <responseCode>00</responseCode>
            <responseText>Approved</responseText>
            <successful>yes</successful>
            <amount>1400</amount>
            <txnID>011700</txnID>
            <CreditCardInfo>
              <pan>424242...242</pan>
              <expiryDate>09/08</expiryDate>
              <recurringFlag>no</recurringFlag>
              <cardType>6</cardType>
              <cardDescription>Visa</cardDescription>
            </CreditCardInfo>
            <settlementDate>20041007</settlementDate>
          </PeriodicItem>
        </PeriodicList>
      </Periodic>
    </SecurePayMessage>
XML;
  }

  function failed_login_response() {
    return '<SecurePayMessage><Status><statusCode>504</statusCode><statusDescription>Invalid merchant ID</statusDescription></Status></SecurePayMessage>';
  }

  function successful_purchase_response() {
    return <<<XML
    <?xml version="1.0" encoding="UTF-8"?>
    <SecurePayMessage>
      <MessageInfo>
        <messageID>8af793f9af34bea0cf40f5fb5c630c</messageID>
        <messageTimestamp>20080802041625665000+660</messageTimestamp>
        <apiVersion>xml-4.2</apiVersion>
      </MessageInfo>
      <RequestType>Payment</RequestType>
      <MerchantInfo>
        <merchantID>XYZ0001</merchantID>
      </MerchantInfo>
      <Status>
        <statusCode>000</statusCode>
        <statusDescription>Normal</statusDescription>
      </Status>
      <Payment>
        <TxnList count="1">
          <Txn ID="1">
            <txnType>0</txnType>
            <txnSource>0</txnSource>
            <amount>1000</amount>
            <currency>AUD</currency>
            <purchaseOrderNo>test</purchaseOrderNo>
            <approved>Yes</approved>
            <responseCode>00</responseCode>
            <responseText>Approved</responseText>
            <thinlinkResponseCode>100</thinlinkResponseCode>
            <thinlinkResponseText>000</thinlinkResponseText>
            <thinlinkEventStatusCode>000</thinlinkEventStatusCode>
            <thinlinkEventStatusText>Normal</thinlinkEventStatusText>
            <settlementDate>20080208</settlementDate>
            <txnID>024259</txnID>
            <CreditCardInfo>
              <pan>424242...242</pan>
              <expiryDate>07/11</expiryDate>
              <cardType>6</cardType>
              <cardDescription>Visa</cardDescription>
            </CreditCardInfo>
          </Txn>
        </TxnList>
      </Payment>
    </SecurePayMessage>
XML;
  }

  function failed_purchase_response() {
    return <<<XML
    <?xml version="1.0" encoding="UTF-8"?>
    <SecurePayMessage>
      <MessageInfo>
        <messageID>8af793f9af34bea0cf40f5fb5c630c</messageID>
        <messageTimestamp>20080802040346380000+660</messageTimestamp>
        <apiVersion>xml-4.2</apiVersion>
      </MessageInfo>
      <RequestType>Payment</RequestType>
      <MerchantInfo>
        <merchantID>XYZ0001</merchantID>
      </MerchantInfo>
      <Status>
        <statusCode>000</statusCode>
        <statusDescription>Normal</statusDescription>
      </Status>
      <Payment>
        <TxnList count="1">
          <Txn ID="1">
            <txnType>0</txnType>
            <txnSource>0</txnSource>
            <amount>1000</amount>
            <currency>AUD</currency>
            <purchaseOrderNo>test</purchaseOrderNo>
            <approved>No</approved>
            <responseCode>907</responseCode>
            <responseText>CARD EXPIRED</responseText>
            <thinlinkResponseCode>300</thinlinkResponseCode>
            <thinlinkResponseText>000</thinlinkResponseText>
            <thinlinkEventStatusCode>981</thinlinkEventStatusCode>
            <thinlinkEventStatusText>Error - Expired Card</thinlinkEventStatusText>
            <settlementDate>        </settlementDate>
            <txnID>000000</txnID>
            <CreditCardInfo>
              <pan>424242...242</pan>
              <expiryDate>07/06</expiryDate>
              <cardType>6</cardType>
              <cardDescription>Visa</cardDescription>
            </CreditCardInfo>
          </Txn>
        </TxnList>
      </Payment>
    </SecurePayMessage>
XML;
  }

  function failed_authorization_response() {
    return <<<XML
    <?xml version="1.0" encoding="UTF-8" standalone="no"?><SecurePayMessage><MessageInfo><messageID>97991d10eda9ae47d684ae21089b97</messageID><messageTimestamp>20102807071237345000+600</messageTimestamp><apiVersion>xml-4.2</apiVersion></MessageInfo><RequestType>Payment</RequestType><MerchantInfo><merchantID>CAX0001</merchantID></MerchantInfo><Status><statusCode>000</statusCode><statusDescription>Normal</statusDescription></Status><Payment><TxnList count="1"><Txn ID="1"><txnType>10</txnType><txnSource>23</txnSource><amount>151</amount><currency>AUD</currency><purchaseOrderNo>1</purchaseOrderNo><approved>No</approved><responseCode>51</responseCode><responseText>Insufficient Funds</responseText><thinlinkResponseCode>200</thinlinkResponseCode><thinlinkResponseText>000</thinlinkResponseText><thinlinkEventStatusCode>000</thinlinkEventStatusCode><thinlinkEventStatusText>Normal</thinlinkEventStatusText><settlementDate>20100728</settlementDate><txnID>269059</txnID><preauthID>269059</preauthID><CreditCardInfo><pan>444433...111</pan><expiryDate>09/11</expiryDate><cardType>6</cardType><cardDescription>Visa</cardDescription></CreditCardInfo></Txn></TxnList></Payment></SecurePayMessage>
XML;
  }

  function successful_capture_response() {
    return <<<XML
    <?xml version="1.0" encoding="UTF-8" standalone="no"?><SecurePayMessage><MessageInfo><messageID>1e3b82037a228c237cbc89db8a5e8a</messageID><messageTimestamp>20102807071233509000+600</messageTimestamp><apiVersion>xml-4.2</apiVersion></MessageInfo><RequestType>Payment</RequestType><MerchantInfo><merchantID>CAX0001</merchantID></MerchantInfo><Status><statusCode>000</statusCode><statusDescription>Normal</statusDescription></Status><Payment><TxnList count="1"><Txn ID="1"><txnType>11</txnType><txnSource>23</txnSource><amount>100</amount><currency>AUD</currency><purchaseOrderNo>1</purchaseOrderNo><approved>Yes</approved><responseCode>00</responseCode><responseText>Approved</responseText><thinlinkResponseCode>100</thinlinkResponseCode><thinlinkResponseText>000</thinlinkResponseText><thinlinkEventStatusCode>000</thinlinkEventStatusCode><thinlinkEventStatusText>Normal</thinlinkEventStatusText><settlementDate>20100728</settlementDate><txnID>269058</txnID><CreditCardInfo><pan>444433...111</pan><expiryDate>09/11</expiryDate><cardType>6</cardType><cardDescription>Visa</cardDescription></CreditCardInfo></Txn></TxnList></Payment></SecurePayMessage>
XML;
  }

  function failed_capture_response() {
    return <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="no"?><SecurePayMessage><MessageInfo><messageID>9ac0da93c0ea7a2d74c2430a078995</messageID><messageTimestamp>20102807071243261000+600</messageTimestamp><apiVersion>xml-4.2</apiVersion></MessageInfo><RequestType>Payment</RequestType><MerchantInfo><merchantID>CAX0001</merchantID></MerchantInfo><Status><statusCode>000</statusCode><statusDescription>Normal</statusDescription></Status><Payment><TxnList count="1"><Txn ID="1"><txnType>11</txnType><txnSource>23</txnSource><amount>101</amount><currency>AUD</currency><purchaseOrderNo>1</purchaseOrderNo><approved>No</approved><responseCode>142</responseCode><responseText>Preauth was done for smaller amount</responseText><thinlinkResponseCode>300</thinlinkResponseCode><thinlinkResponseText>000</thinlinkResponseText><thinlinkEventStatusCode>999</thinlinkEventStatusCode><thinlinkEventStatusText>Error - Pre-auth Was Done For Smaller Amount</thinlinkEventStatusText><settlementDate/><txnID/><CreditCardInfo><pan>444433...111</pan><expiryDate>09/11</expiryDate><cardType>6</cardType><cardDescription>Visa</cardDescription></CreditCardInfo></Txn></TxnList></Payment></SecurePayMessage>
XML;
  }

  function successful_void_response() {
    return <<<XML
    <?xml version="1.0" encoding="UTF-8" standalone="no"?><SecurePayMessage><MessageInfo><messageID>2207c9396eb7005639edcbae9bfb46</messageID><messageTimestamp>20102807071317401000+600</messageTimestamp><apiVersion>xml-4.2</apiVersion></MessageInfo><RequestType>Payment</RequestType><MerchantInfo><merchantID>CAX0001</merchantID></MerchantInfo><Status><statusCode>000</statusCode><statusDescription>Normal</statusDescription></Status><Payment><TxnList count="1"><Txn ID="1"><txnType>6</txnType><txnSource>23</txnSource><amount>100</amount><currency>AUD</currency><purchaseOrderNo>269069</purchaseOrderNo><approved>Yes</approved><responseCode>00</responseCode><responseText>Approved</responseText><thinlinkResponseCode>100</thinlinkResponseCode><thinlinkResponseText>000</thinlinkResponseText><thinlinkEventStatusCode>000</thinlinkEventStatusCode><thinlinkEventStatusText>Normal</thinlinkEventStatusText><settlementDate>20100728</settlementDate><txnID>269070</txnID><CreditCardInfo><pan>444433...111</pan><expiryDate>09/11</expiryDate><cardType>6</cardType><cardDescription>Visa</cardDescription></CreditCardInfo></Txn></TxnList></Payment></SecurePayMessage>
XML;
  }

  function failed_void_response() {
    return <<<XML
    <?xml version="1.0" encoding="UTF-8" standalone="no"?><SecurePayMessage><MessageInfo><messageID>5ae52d17168291fff92d0c45933eb5</messageID><messageTimestamp>20102807071257719000+600</messageTimestamp><apiVersion>xml-4.2</apiVersion></MessageInfo><RequestType>Payment</RequestType><MerchantInfo><merchantID>CAX0001</merchantID></MerchantInfo><Status><statusCode>000</statusCode><statusDescription>Normal</statusDescription></Status><Payment><TxnList count="1"><Txn ID="1"><txnType>6</txnType><txnSource>23</txnSource><amount>1001</amount><currency>AUD</currency><purchaseOrderNo>269063</purchaseOrderNo><approved>No</approved><responseCode>100</responseCode><responseText>Transaction was done for different amount</responseText><thinlinkResponseCode>300</thinlinkResponseCode><thinlinkResponseText>000</thinlinkResponseText><thinlinkEventStatusCode>990</thinlinkEventStatusCode><thinlinkEventStatusText>Error - Invalid amount</thinlinkEventStatusText><settlementDate/><txnID/><CreditCardInfo><pan>444433...111</pan><expiryDate>09/11</expiryDate><cardType>6</cardType><cardDescription>Visa</cardDescription></CreditCardInfo></Txn></TxnList></Payment></SecurePayMessage>
XML;
  }

  function successful_refund_response() {
    return <<<XML
    <?xml version="1.0" encoding="UTF-8" standalone="no"?><SecurePayMessage><MessageInfo><messageID>feaedbe87239a005729aece8efa48b</messageID><messageTimestamp>20102807071306650000+600</messageTimestamp><apiVersion>xml-4.2</apiVersion></MessageInfo><RequestType>Payment</RequestType><MerchantInfo><merchantID>CAX0001</merchantID></MerchantInfo><Status><statusCode>000</statusCode><statusDescription>Normal</statusDescription></Status><Payment><TxnList count="1"><Txn ID="1"><txnType>4</txnType><txnSource>23</txnSource><amount>100</amount><currency>AUD</currency><purchaseOrderNo>269065</purchaseOrderNo><approved>Yes</approved><responseCode>00</responseCode><responseText>Approved</responseText><thinlinkResponseCode>100</thinlinkResponseCode><thinlinkResponseText>000</thinlinkResponseText><thinlinkEventStatusCode>000</thinlinkEventStatusCode><thinlinkEventStatusText>Normal</thinlinkEventStatusText><settlementDate>20100728</settlementDate><txnID>269067</txnID><CreditCardInfo><pan>444433...111</pan><expiryDate>09/11</expiryDate><cardType>6</cardType><cardDescription>Visa</cardDescription></CreditCardInfo></Txn></TxnList></Payment></SecurePayMessage>
XML;
  }

  function failed_refund_response() {
    return <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="no"?><SecurePayMessage><MessageInfo><messageID>6bacab2b7ae1200d8099e0873e25bc</messageID><messageTimestamp>20102807071248484000+600</messageTimestamp><apiVersion>xml-4.2</apiVersion></MessageInfo><RequestType>Payment</RequestType><MerchantInfo><merchantID>CAX0001</merchantID></MerchantInfo><Status><statusCode>000</statusCode><statusDescription>Normal</statusDescription></Status><Payment><TxnList count="1"><Txn ID="1"><txnType>4</txnType><txnSource>23</txnSource><amount>101</amount><currency>AUD</currency><purchaseOrderNo>269061</purchaseOrderNo><approved>No</approved><responseCode>134</responseCode><responseText>Only $1.00 available for refund</responseText><thinlinkResponseCode>300</thinlinkResponseCode><thinlinkResponseText>000</thinlinkResponseText><thinlinkEventStatusCode>999</thinlinkEventStatusCode><thinlinkEventStatusText>Error - Transaction Already Fully Refunded/Only \$x.xx Available for Refund</thinlinkEventStatusText><settlementDate/><txnID/><CreditCardInfo><pan>444433...111</pan><expiryDate>09/11</expiryDate><cardType>6</cardType><cardDescription>Visa</cardDescription></CreditCardInfo></Txn></TxnList></Payment></SecurePayMessage>
XML;
  }
}
