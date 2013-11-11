<?php

require_once "config.php";

use AktiveMerchant\Billing\Gateways\PaymentExpress;
use AktiveMerchant\Billing\Base;
use AktiveMerchant\Billing\CreditCard;

class PaymentExpressTest extends \AktiveMerchant\TestCase {
  function credit_card($data = []) {
    return new CreditCard(
        $data + array(
            "first_name" => "John",
            "last_name" => "Doe",
            "number" => "4444333322221111",
            "month" => "01",
            "year" => "2015",
            "verification_value" => "000"
        )
    );
  }
  function setup() {
    $this->gateway =  new PaymentExpress([
      'login' => 'LOGIN',
      'password' => 'PASSWORD'
    ]);

    $this->visa = $this->credit_card();

    $this->solo = $this->credit_card(["6334900000000005", 'brand' => "solo", 'issue_number' => '01']);

    $this->options = [
      'order_id' => $this->gateway->generateUniqueId(),
      'billing_address' => $this->address(),
      'email' => 'cody@example.com',
      'description' => 'Store purchase'
    ];

    $this->amount = 100;
  }

  function mock_gateway($action) {
    $gateway = $this->getMock('AktiveMerchant\Billing\Gateways\PaymentExpress', ["ssl_post"], [[
      'login' => 'LOGIN',
      'password' => 'PASSWORD'
    ]]);
    if($action instanceof \Closure) {
      $gateway->expects($this->once())->method("ssl_post")->will($this->returnCallback($action));
    } else {
      $gateway->expects($this->once())->method("ssl_post")->will($this->returnValue($action));
    }
    return $gateway;
  }

  function test_default_currency() {
    $this->assertEquals('NZD', PaymentExpress::$default_currency);
  }

  function test_invalid_credentials() {
    $gateway = $this->mock_gateway($this->invalid_credentials_response());

    $this->assertNotNull($response = $gateway->purchase($this->amount, $this->visa, $this->options));
    $this->assertEquals('The transaction was Declined (AG)', $response->message());
    $this->assert_failure($response);
  }

  function test_successful_authorization() {
    $gateway = $this->mock_gateway($this->successful_authorization_response());

    $this->assertNotNull($response = $gateway->purchase($this->amount, $this->visa, $this->options));

    $this->assert_success($response);
    $this->assertNotNull($response->test());
    $this->assertEquals('The Transaction was approved', $response->message());
    $this->assertEquals('00000004011a2478', $response->authorization());
  }

# function test_purchase_request_should_include_cvc2_presence() {
#   $this->gateway->expects('commit')->with do |type, request|
#     type == 'purchase' && request->to_s =~ %r{<Cvc2Presence>1<\/Cvc2Presence>}
#   }

#   $this->gateway->purchase($this->amount, $this->visa, $this->options)
# }

  function test_successful_solo_authorization() {
    $gateway = $this->mock_gateway($this->successful_authorization_response());

    $this->assertNotNull($response = $gateway->purchase($this->amount, $this->solo, $this->options));
    $this->assert_success($response);
    $this->asserttrue($response->test());
    $this->assertEquals('The Transaction was approved', $response->message());
    $this->assertEquals('00000004011a2478', $response->authorization());
  }

  function test_successful_card_store() {
    $gateway = $this->mock_gateway($this->successful_store_response());

    $this->assertNotNull($response = $gateway->store($this->visa));
    $this->assert_success($response);
    $this->assertTrue($response->test());
    $this->assertEquals('0000030000141581', $response->token());
  }

  function test_successful_card_store_with_custom_billing_id() {
    $gateway = $this->mock_gateway($this->successful_store_response(['billing_id' => "my-custom-id"]));

    $this->assertnotNull($response = $gateway->store($this->visa, ['billing_id' => "my-custom-id"]));
    $this->assert_success($response);
    $this->asserttrue($response->test());
    $this->assertEquals('my-custom-id', $response->token());
  }

  function test_unsuccessful_card_store() {
    $gateway = $this->mock_gateway($this->unsuccessful_store_response());

    $this->visa->number = 2;

    $this->assertNotNull($response = $gateway->store($this->visa));
    $this->assert_failure($response);
  }

/*
  function test_purchase_using_dps_billing_id_token() {
    $gateway = $this->mock_gateway($this->successful_store_response());

    $this->assertNotNull($response = $gateway->store($this->visa));
    $token = $response->token();

    $gateway = $this->mock_gateway($this->successful_dps_billing_id_token_purchase_response());

    $this->assertNotNull($response = $gateway->purchase($this->amount, $token, $this->options));
    $this->assert_success($response);
    $this->assertEquals('The Transaction was approved', $response->message());
    $this->assertEquals('0000000303ace8db', $response->authorization());
  }

  function test_purchase_using_merchant_specified_billing_id_token() {
    $this->gateway = new PaymentExpress([jj
      'login' => 'LOGIN',
      'password' => 'PASSWORD',
      'use_custom_payment_token' => true
    )
    $gateway = $this->mock_gateway(

    $this->gateway->expects('ssl_post')->returns( successful_store_response({'billing_id' => 'TEST1234'}) )

    $this->assertNotNull($response = $this->gateway->store($this->visa, {'billing_id' => 'TEST1234'}));
    $this->assertEquals('TEST1234', $response->token());

    $this->gateway->expects('ssl_post')->returns( successful_billing_id_token_purchase_response )

    $this->assertNotNull($response = $this->gateway->purchase($this->amount, 'TEST1234', $this->options));
    $this->assert_success($response);
    $this->assertEquals('The Transaction was approved', $response->message());
    $this->assertEquals('0000000303ace8db', $response->authorization());
  }
  */

  function test_supported_countries() {
    $this->assertEquals(explode(" ", "AU CA DE ES FR GB HK IE MY NL NZ SG US ZA"), PaymentExpress::$supported_countries);
  }

  function test_supported_card_types() {
    $this->assertEquals([ 'visa', 'master', 'american_express', 'diners_club', 'jcb' ], PaymentExpress::$supported_cardtypes);
  }

  function test_avs_result_not_supported() {
    $gateway = $this->mock_gateway($this->successful_authorization_response());

    $response = $gateway->purchase($this->amount, $this->visa, $this->options);
    $this->assertNull($response->avs_result['code']);
  }

  function test_cvv_result_not_supported() {
    $gateway = $this->mock_gateway($this->successful_authorization_response());

    $response = $gateway->purchase($this->amount, $this->visa, $this->options);
    $this->assertNull($response->avs_result['code']);
  }

  function test_expect_no_optional_fields_by_default() {
    $this->perform_each_transaction_type_with_request_body_assertions(array(), function($url, $body) {
      $this->assertNotContains("<ClientType>", $body);
      $this->assertNotContains("<TxnData1>", $body);
      $this->assertNotContains("<TxnData2>", $body);
      $this->assertNotContains("<TxnData3>", $body);
    });
  }

  function test_pass_optional_txn_data() {
    $options = [
      'txn_data1' => "Transaction Data 1",
      'txn_data2' => "Transaction Data 2",
      'txn_data3' => "Transaction Data 3"
    ];

    $this->perform_each_transaction_type_with_request_body_assertions($options, function($url, $body) {
      $this->assertContains("<TxnData1>Transaction Data 1</TxnData1>", $body);
      $this->assertContains("<TxnData2>Transaction Data 2</TxnData2>", $body);
      $this->assertContains("<TxnData3>Transaction Data 3</TxnData3>", $body);
    });
  }

  function test_pass_optional_txn_data_truncated_to_255_chars() {
    $options = [
      'txn_data1' => "Transaction Data 1-01234567890123456789012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345-EXTRA",
      'txn_data2' => "Transaction Data 2-01234567890123456789012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345-EXTRA",
      'txn_data3' => "Transaction Data 3-01234567890123456789012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345-EXTRA"
    ];

    $truncated_addendum = "01234567890123456789012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345";

    $this->perform_each_transaction_type_with_request_body_assertions($options, function($url, $body) use ($truncated_addendum) {
      $this->assertContains("<TxnData1>Transaction Data 1-$truncated_addendum</TxnData1>", $body);
      $this->assertContains("<TxnData2>Transaction Data 2-$truncated_addendum</TxnData2>", $body);
      $this->assertContains("<TxnData3>Transaction Data 3-$truncated_addendum</TxnData3>", $body);
    });

  }
  function test_purchase_truncates_order_id_to_16_chars() {
    $gateway = $this->mock_gateway(function($url, $data) {
      $this->assertContains("<TxnId>16chars---------</TxnId>", $data);
      return $this->successful_authorization_response();
    });
    
    $gateway->purchase($this->amount, $this->visa, ['order_id' => "16chars---------EXTRA"]);
  }

  function test_authorize_truncates_order_id_to_16_chars() {
    $gateway = $this->mock_gateway(function($url, $data) {
      $this->assertContains("<TxnId>16chars---------</TxnId>", $data);
      return $this->successful_authorization_response();
    });
    
    $gateway->authorize($this->amount, $this->visa, ['order_id' => "16chars---------EXTRA"]);
  }

  function test_capture_truncates_order_id_to_16_chars() {
    $gateway = $this->mock_gateway(function($url, $data) {
      $this->assertContains("<TxnId>16chars---------</TxnId>", $data);
      return $this->successful_authorization_response();
    });
    
    $gateway->capture($this->amount, "identification", ['order_id' => "16chars---------EXTRA"]);
  }

  function test_refund_truncates_order_id_to_16_chars() {
    $gateway = $this->mock_gateway(function($url, $data) {
      $this->assertContains("<TxnId>16chars---------</TxnId>", $data);
      return $this->successful_authorization_response();
    });
    
    $gateway->refund($this->amount, "identification", ["description" => "whatever", 'order_id' => "16chars---------EXTRA"]);
  }

  function test_purchase_truncates_description_to_50_chars() {
    $gateway = $this->mock_gateway(function($url, $data, $headers) {
      $this->assertContains("<MerchantReference>50chars-------------------------------------------</MerchantReference>", $data);
   
      return $this->successful_authorization_response();
    });
    $gateway->purchase($this->amount, $this->credit_card(), ['description' => "50chars-------------------------------------------EXTRA"]);
  }

  function test_authorize_truncates_description_to_50_chars() {
    $gateway = $this->mock_gateway(function($url, $data, $headers) {
      $this->assertContains("<MerchantReference>50chars-------------------------------------------</MerchantReference>", $data);
   
      return $this->successful_authorization_response();
    });
    $gateway->authorize($this->amount, $this->credit_card(), ['description' => "50chars-------------------------------------------EXTRA"]);
  }

  function test_capture_truncates_description_to_50_chars() {
    $gateway = $this->mock_gateway(function($url, $data, $headers) {
      $this->assertContains("<MerchantReference>50chars-------------------------------------------</MerchantReference>", $data);
   
      return $this->successful_authorization_response();
    });
    $gateway->capture($this->amount, 'identification', ['description' => "50chars-------------------------------------------EXTRA"]);
  }

  function test_refund_truncates_description_to_50_chars() {
    $gateway = $this->mock_gateway(function($url, $data, $headers) {
      $this->assertContains("<MerchantReference>50chars-------------------------------------------</MerchantReference>", $data);
   
      return $this->successful_authorization_response();
    });
    $gateway->refund($this->amount, 'identification', ['description' => "50chars-------------------------------------------EXTRA"]);
  }

  private

  function perform_each_transaction_type_with_request_body_assertions($options = [], $check = null) {
    if(!$check) $check = function() {};

    $gateway = $this->mock_gateway(function() use ($check) {
      $arguments = func_get_args();
      call_user_func_array($check, $arguments);
      return $this->successful_authorization_response();
    });

    $gateway->purchase($this->amount, $this->visa, $options);

    # authorize
    $gateway = $this->mock_gateway(function() use ($check) {

      $arguments = func_get_args();
      call_user_func_array($check, $arguments);
      return $this->successful_authorization_response();
    });
    $gateway->authorize($this->amount, $this->visa, $options);

    # capture
    $gateway = $this->mock_gateway(function() use ($check) {
      $arguments = func_get_args();
      call_user_func_array($check, $arguments);

      return $this->successful_authorization_response();
    });
    $gateway->capture($this->amount, 'identification', $options);

    # this->refund
    $gateway = $this->mock_gateway(function() use ($check) {
      $arguments = func_get_args();
      call_user_func_array($check, $arguments);

      return $this->successful_authorization_response();
    });
    $gateway->refund($this->amount, 'identification', $options + ['description' => "description"]);

    $gateway = $this->mock_gateway(function() use ($check) {
      $arguments = func_get_args();
      call_user_func_array($check, $arguments);

      return $this->successful_store_response();
    });
    $gateway->store($this->visa, $options);
  }

   function billing_id_token_purchase($options = []) {
    return "<Txn><BillingId>{$options['billing_id']}</BillingId><Amount>1.00</Amount><InputCurrency>NZD</InputCurrency><TxnId>aaa050be9488e8e4</TxnId><MerchantReference>Store purchase</MerchantReference><EnableAvsData>1</EnableAvsData><AvsAction>1</AvsAction><AvsStreetAddress>1234 My Street</AvsStreetAddress><AvsPostCode>K1C2N6</AvsPostCode><PostUsername>LOGIN</PostUsername><PostPassword>PASSWORD</PostPassword><TxnType>Purchase</TxnType></Txn>";
  }

  function invalid_credentials_response() {
    return '<Txn><ReCo>0</ReCo><ResponseText>Invalid Credentials</ResponseText><CardHolderHelpText>The transaction was Declined (AG)</CardHolderHelpText></Txn>';
  }

  function successful_authorization_response() {
    return <<<RESPONSE
<Txn>
  <Transaction success="1" reco="00" responsetext="APPROVED">
    <Authorized>1</Authorized>
    <MerchantReference>Test Transaction</MerchantReference>
    <Cvc2>M</Cvc2>
    <CardName>Visa</CardName>
    <Retry>0</Retry>
    <StatusRequired>0</StatusRequired>
    <AuthCode>015921</AuthCode>
    <Amount>1.23</Amount>
    <InputCurrencyId>1</InputCurrencyId>
    <InputCurrencyName>NZD</InputCurrencyName>
    <Acquirer>WestpacTrust</Acquirer>
    <CurrencyId>1</CurrencyId>
    <CurrencyName>NZD</CurrencyName>
    <CurrencyRate>1.00</CurrencyRate>
    <Acquirer>WestpacTrust</Acquirer>
    <AcquirerDate>30102000</AcquirerDate>
    <AcquirerId>1</AcquirerId>
    <CardHolderName>DPS</CardHolderName>
    <DateSettlement>20050811</DateSettlement>
    <TxnType>Purchase</TxnType>
    <CardNumber>411111</CardNumber>
    <DateExpiry>0807</DateExpiry>
    <ProductId></ProductId>
    <AcquirerDate>20050811</AcquirerDate>
    <AcquirerTime>060039</AcquirerTime>
    <AcquirerId>9000</AcquirerId>
    <Acquirer>Test</Acquirer>
    <TestMode>1</TestMode>
    <CardId>2</CardId>
    <CardHolderResponseText>APPROVED</CardHolderResponseText>
    <CardHolderHelpText>The Transaction was approved</CardHolderHelpText>
    <CardHolderResponseDescription>The Transaction was approved</CardHolderResponseDescription>
    <MerchantResponseText>APPROVED</MerchantResponseText>
    <MerchantHelpText>The Transaction was approved</MerchantHelpText>
    <MerchantResponseDescription>The Transaction was approved</MerchantResponseDescription>
    <GroupAccount>9997</GroupAccount>
    <DpsTxnRef>00000004011a2478</DpsTxnRef>
    <AllowRetry>0</AllowRetry>
    <DpsBillingId></DpsBillingId>
    <BillingId></BillingId>
    <TransactionId>011a2478</TransactionId>
  </Transaction>
  <ReCo>00</ReCo>
  <ResponseText>APPROVED</ResponseText>
  <HelpText>The Transaction was approved</HelpText>
  <Success>1</Success>
  <TxnRef>00000004011a2478</TxnRef>
</Txn>
RESPONSE;
  }

   function successful_store_response($options = []) {
    return "<Txn><Transaction success='1' reco='00' responsetext='APPROVED'><Authorized>1</Authorized><MerchantReference></MerchantReference><CardName>Visa</CardName><Retry>0</Retry><StatusRequired>0</StatusRequired><AuthCode>02381203accf5c00000003</AuthCode><Amount>0.01</Amount><CurrencyId>554</CurrencyId><InputCurrencyId>554</InputCurrencyId><InputCurrencyName>NZD</InputCurrencyName><CurrencyRate>1.00</CurrencyRate><CurrencyName>NZD</CurrencyName><CardHolderName>BOB BOBSEN</CardHolderName><DateSettlement>20070323</DateSettlement><TxnType>Auth</TxnType><CardNumber>424242........42</CardNumber><DateExpiry>0809</DateExpiry><ProductId></ProductId><AcquirerDate>20070323</AcquirerDate><AcquirerTime>023812</AcquirerTime><AcquirerId>9000</AcquirerId><Acquirer>Test</Acquirer><TestMode>1</TestMode><CardId>2</CardId><CardHolderResponseText>APPROVED</CardHolderResponseText><CardHolderHelpText>The Transaction was approved</CardHolderHelpText><CardHolderResponseDescription>The Transaction was approved</CardHolderResponseDescription><MerchantResponseText>APPROVED</MerchantResponseText><MerchantHelpText>The Transaction was approved</MerchantHelpText><MerchantResponseDescription>The Transaction was approved</MerchantResponseDescription><UrlFail></UrlFail><UrlSuccess></UrlSuccess><EnablePostResponse>0</EnablePostResponse><PxPayName></PxPayName><PxPayLogoSrc></PxPayLogoSrc><PxPayUserId></PxPayUserId><PxPayXsl></PxPayXsl><PxPayBgColor></PxPayBgColor><AcquirerPort>9999999999-99999999</AcquirerPort><AcquirerTxnRef>12835</AcquirerTxnRef><GroupAccount>9997</GroupAccount><DpsTxnRef>0000000303accf5c</DpsTxnRef><AllowRetry>0</AllowRetry><DpsBillingId>0000030000141581</DpsBillingId><BillingId>".@$options['billing_id']."</BillingId><TransactionId>03accf5c</TransactionId><PxHostId>00000003</PxHostId></Transaction><ReCo>00</ReCo><ResponseText>APPROVED</ResponseText><HelpText>The Transaction was approved</HelpText><Success>1</Success><DpsTxnRef>0000000303accf5c</DpsTxnRef><TxnRef></TxnRef></Txn>";
  }

   function unsuccessful_store_response($options = []) {
    return "<Txn><Transaction success='0' reco='QK' responsetext='INVALID CARD NUMBER'><Authorized>0</Authorized><MerchantReference></MerchantReference><CardName></CardName><Retry>0</Retry><StatusRequired>0</StatusRequired><AuthCode></AuthCode><Amount>0.01</Amount><CurrencyId>554</CurrencyId><InputCurrencyId>554</InputCurrencyId><InputCurrencyName>NZD</InputCurrencyName><CurrencyRate>1.00</CurrencyRate><CurrencyName>NZD</CurrencyName><CardHolderName>LONGBOB LONGSEN</CardHolderName><DateSettlement>19800101</DateSettlement><TxnType>Validate</TxnType><CardNumber>000000........00</CardNumber><DateExpiry>0808</DateExpiry><ProductId></ProductId><AcquirerDate></AcquirerDate><AcquirerTime></AcquirerTime><AcquirerId>9000</AcquirerId><Acquirer></Acquirer><TestMode>0</TestMode><CardId>0</CardId><CardHolderResponseText>INVALID CARD NUMBER</CardHolderResponseText><CardHolderHelpText>An Invalid Card Number was entered. Check the card number</CardHolderHelpText><CardHolderResponseDescription>An Invalid Card Number was entered. Check the card number</CardHolderResponseDescription><MerchantResponseText>INVALID CARD NUMBER</MerchantResponseText><MerchantHelpText>An Invalid Card Number was entered. Check the card number</MerchantHelpText><MerchantResponseDescription>An Invalid Card Number was entered. Check the card number</MerchantResponseDescription><UrlFail></UrlFail><UrlSuccess></UrlSuccess><EnablePostResponse>0</EnablePostResponse><PxPayName></PxPayName><PxPayLogoSrc></PxPayLogoSrc><PxPayUserId></PxPayUserId><PxPayXsl></PxPayXsl><PxPayBgColor></PxPayBgColor><AcquirerPort>9999999999-99999999</AcquirerPort><AcquirerTxnRef>0</AcquirerTxnRef><GroupAccount>9997</GroupAccount><DpsTxnRef></DpsTxnRef><AllowRetry>0</AllowRetry><DpsBillingId></DpsBillingId><BillingId></BillingId><TransactionId>00000000</TransactionId><PxHostId>00000003</PxHostId></Transaction><ReCo>QK</ReCo><ResponseText>INVALID CARD NUMBER</ResponseText><HelpText>An Invalid Card Number was entered. Check the card number</HelpText><Success>0</Success><DpsTxnRef></DpsTxnRef><TxnRef></TxnRef></Txn>";
  }

  function successful_dps_billing_id_token_purchase_response() {
    return "<Txn><Transaction success='1' reco='00' responsetext='APPROVED'><Authorized>1</Authorized><MerchantReference></MerchantReference><CardName>Visa</CardName><Retry>0</Retry><StatusRequired>0</StatusRequired><AuthCode>030817</AuthCode><Amount>10.00</Amount><CurrencyId>554</CurrencyId><InputCurrencyId>554</InputCurrencyId><InputCurrencyName>NZD</InputCurrencyName><CurrencyRate>1.00</CurrencyRate><CurrencyName>NZD</CurrencyName><CardHolderName>LONGBOB LONGSEN</CardHolderName><DateSettlement>20070323</DateSettlement><TxnType>Purchase</TxnType><CardNumber>424242........42</CardNumber><DateExpiry>0808</DateExpiry><ProductId></ProductId><AcquirerDate>20070323</AcquirerDate><AcquirerTime>030817</AcquirerTime><AcquirerId>9000</AcquirerId><Acquirer>Test</Acquirer><TestMode>1</TestMode><CardId>2</CardId><CardHolderResponseText>APPROVED</CardHolderResponseText><CardHolderHelpText>The Transaction was approved</CardHolderHelpText><CardHolderResponseDescription>The Transaction was approved</CardHolderResponseDescription><MerchantResponseText>APPROVED</MerchantResponseText><MerchantHelpText>The Transaction was approved</MerchantHelpText><MerchantResponseDescription>The Transaction was approved</MerchantResponseDescription><UrlFail></UrlFail><UrlSuccess></UrlSuccess><EnablePostResponse>0</EnablePostResponse><PxPayName></PxPayName><PxPayLogoSrc></PxPayLogoSrc><PxPayUserId></PxPayUserId><PxPayXsl></PxPayXsl><PxPayBgColor></PxPayBgColor><AcquirerPort>9999999999-99999999</AcquirerPort><AcquirerTxnRef>12859</AcquirerTxnRef><GroupAccount>9997</GroupAccount><DpsTxnRef>0000000303ace8db</DpsTxnRef><AllowRetry>0</AllowRetry><DpsBillingId>0000030000141581</DpsBillingId><BillingId></BillingId><TransactionId>03ace8db</TransactionId><PxHostId>00000003</PxHostId></Transaction><ReCo>00</ReCo><ResponseText>APPROVED</ResponseText><HelpText>The Transaction was approved</HelpText><Success>1</Success><DpsTxnRef>0000000303ace8db</DpsTxnRef><TxnRef></TxnRef></Txn>";
  }

  function successful_billing_id_token_purchase_response() {
    return "<Txn><Transaction success='1' reco='00' responsetext='APPROVED'><Authorized>1</Authorized><MerchantReference></MerchantReference><CardName>Visa</CardName><Retry>0</Retry><StatusRequired>0</StatusRequired><AuthCode>030817</AuthCode><Amount>10.00</Amount><CurrencyId>554</CurrencyId><InputCurrencyId>554</InputCurrencyId><InputCurrencyName>NZD</InputCurrencyName><CurrencyRate>1.00</CurrencyRate><CurrencyName>NZD</CurrencyName><CardHolderName>LONGBOB LONGSEN</CardHolderName><DateSettlement>20070323</DateSettlement><TxnType>Purchase</TxnType><CardNumber>424242........42</CardNumber><DateExpiry>0808</DateExpiry><ProductId></ProductId><AcquirerDate>20070323</AcquirerDate><AcquirerTime>030817</AcquirerTime><AcquirerId>9000</AcquirerId><Acquirer>Test</Acquirer><TestMode>1</TestMode><CardId>2</CardId><CardHolderResponseText>APPROVED</CardHolderResponseText><CardHolderHelpText>The Transaction was approved</CardHolderHelpText><CardHolderResponseDescription>The Transaction was approved</CardHolderResponseDescription><MerchantResponseText>APPROVED</MerchantResponseText><MerchantHelpText>The Transaction was approved</MerchantHelpText><MerchantResponseDescription>The Transaction was approved</MerchantResponseDescription><UrlFail></UrlFail><UrlSuccess></UrlSuccess><EnablePostResponse>0</EnablePostResponse><PxPayName></PxPayName><PxPayLogoSrc></PxPayLogoSrc><PxPayUserId></PxPayUserId><PxPayXsl></PxPayXsl><PxPayBgColor></PxPayBgColor><AcquirerPort>9999999999-99999999</AcquirerPort><AcquirerTxnRef>12859</AcquirerTxnRef><GroupAccount>9997</GroupAccount><DpsTxnRef>0000000303ace8db</DpsTxnRef><AllowRetry>0</AllowRetry><DpsBillingId></DpsBillingId><BillingId>TEST1234</BillingId><TransactionId>03ace8db</TransactionId><PxHostId>00000003</PxHostId></Transaction><ReCo>00</ReCo><ResponseText>APPROVED</ResponseText><HelpText>The Transaction was approved</HelpText><Success>1</Success><DpsTxnRef>0000000303ace8db</DpsTxnRef><TxnRef></TxnRef></Txn>";
  }

}
