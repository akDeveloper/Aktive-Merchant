<?php

require_once "../unit_tests/config.php";

use AktiveMerchant\Billing\Gateways\PaymentExpress;
use AktiveMerchant\Billing\CreditCard;
use AktiveMerchant\Billing\Base;


/**
 * @group Remote
 */
class RemotePaymentExpressTest extends \AktiveMerchant\TestCase {

  function setup() {
    $fixtures = $this->getFixtures();
    $this->gateway = new PaymentExpress($fixtures['payment_express']);

    $this->credit_card = $this->credit_card('4111111111111111');

    $this->options = array(
      'order_id' => $this->gateway->generateUniqueId(),
      'billing_address' => $this->address(),
      'email' => 'cody@example.com',
      'description' => 'Store purchase'
    );
    
    $this->amount = 100;
  }

  function assertSuccess($response) {
    $this->assertTrue($response->success());
  }
  
  function test_successful_purchase() {
    $this->assertNotNull($response = $this->gateway->purchase($this->amount, $this->credit_card, $this->options));
    $this->assertEquals("The Transaction was approved", $response->message());
    $this->assertsuccess($response);
    $this->assertNotNull($response->authorization());
  }
  
  function test_successful_purchase_with_reference_id() {
    $this->assertNotNull($response = $this->gateway->purchase($this->amount, $this->credit_card, $this->options));
    $this->assertEquals("The Transaction was approved", $response->message());
    $this->assertSuccess($response);
    $this->assertNotNull($response->authorization());
  }
  
  function test_declined_purchase() {
    $this->assertNotNull($response = $this->gateway->purchase(176, $this->credit_card, $this->options));
    $this->assertEquals('The transaction was $declined-> Funds were not transferred', $response->message);
    $this->assert_failure($response);
  }
  
  function test_successful_authorization() {
    $this->assertNotNull($response = $this->gateway->authorize($this->amount, $this->credit_card, $this->options));
    $this->assertEquals("The Transaction was approved", $response->message);
    $this->assertsuccess($response);
    $this->assertNotNull($response->authorization);
  }

  function test_authorize_and_capture() {
    $this->assertNotNull($auth = $this->gateway->authorize($this->amount, $this->credit_card, $this->options));
    $this->assertsuccess($auth);
    $this->assertEquals('The Transaction was approved', $auth->message);
    $this->assertNotNull($auth->authorization);
    $this->assertNotNull($capture = $this->gateway->capture($this->amount, $auth->authorization));
    $this->assertsuccess($capture);
  }
  
  function test_purchase_and_credit() {
    $amount = 10000;
    $this->assertNotNull($purchase = $this->gateway->purchase($amount, $this->credit_card, $this->options));
    $this->assertsuccess($purchase);
    $this->assertEquals('The Transaction was approved', $purchase->message);
    $this->assertNotNull(!$purchase->authorization);
    $this->assertNotNull($credit = $this->gateway->credit(amount, $purchase->authorization, array('description' => "Giving a refund")));
    $this->assertsuccess($credit);
  }
  
  function test_failed_capture() {
    $this->assertNotNull($response = $this->gateway->capture($this->amount, '999'));
    $this->assert_failure($response);
    $this->assertEquals('DpsTxnRef Invalid', $response->message);
  }
  
  function test_invalid_login() {
    $gateway = new PaymentExpress(array(
      'login' => '',
      'password' => ''
    ));
    $this->assertNotNull($response = $gateway->purchase($this->amount, $this->credit_card, $this->options));
    $this->assertEquals('The transaction was Declined (AG)', $response->message);
    $this->assert_failure($response);
  }
  
  function test_store_credit_card() {
    $this->assertNotNull($response = $this->gateway->store($this->credit_card));
    $this->assertsuccess($response);
    $this->assertEquals("The Transaction was approved", $response->message);
    $this->assertNotNull(true == $response->token);
    $this->assertNotNull($response->token);
  }
  
  function test_store_with_custom_token() {
    $token = $Time->now->to_i->to_s; #hehe
    $this->assertNotNull($response = $this->gateway->store($this->credit_card, array('billing_id' => token)));
    $this->assertsuccess($response);
    $this->assertEquals("The Transaction was approved", $response->message);
    $this->assertTrue(true == $response->token);
    $this->assertNotNull($response->token);
    $this->assertEquals($token, $response->token);
  }
  
  function test_store_invalid_credit_card() {
    $original_number = $this->credit_card()->number;
    $this->credit_card->number = 2;
  
    $this->assertNotNull($response = $this->gateway->store($this->credit_card));
    $this->assert_failure($response);
    $this->credit_card->number = $original_number;
  }
  
  function test_store_and_charge() {
    $this->assertNotNull($response = $this->gateway->store($this->credit_card));
    $this->assertsuccess($response);
    $this->assertEquals("The Transaction was approved", $response->message);
    $this->assertNotNull($token = $response->token);
    
    $this->assertNotNull($purchase = $this->gateway->purchase( $this->amount, token));
    $this->assertEquals("The Transaction was approved", $purchase->message);
    $this->assertsuccess($purchase);
    $this->assertNotNull($purchase->authorization);
  }
  
  function test_store_and_authorize_and_capture() {
    $this->assertNotNull($response = $this->gateway->store($this->credit_card));
    $this->assertsuccess($response);
    $this->assertEquals("The Transaction was approved", $response->message);
    $this->assertNotNull($token = $response->token);

    $this->assertNotNull($auth = $this->gateway->authorize($this->amount, token, $this->options));
    $this->assertsuccess($auth);
    $this->assertEquals('The Transaction was approved', $auth->message);
    $this->assertNotNull($auth->authorization);
    $this->assertNotNull($capture = $this->gateway->capture($this->amount, $auth->authorization));
    $this->assertsuccess($capture);
  }
  
}
