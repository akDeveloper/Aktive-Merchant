<?php

require_once "../unit_tests/config.php";
use AktiveMerchant\Billing\Gateways\SecurePayAu;
use AktiveMerchant\Billing\CreditCard;
use AktiveMerchant\Billing\StoredCreditCard;
use AktiveMerchant\Billing\Base;


/**
 * @group Remote
 */
class SecurePayAuRemoteTest extends \AktiveMerchant\TestCase {
  

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

  function setup() {
    $fixture = $this->getFixtures();
    $this->gateway =  new SecurePayAu($fixture["secure_pay_au"]);
    Base::mode("test");


    $this->amount = 100;
    $this->credit_card = $this->credit_card('4242424242424242', array('month' => 9, 'year' => 15));

    $this->options = array(
      'order_id' => '2',
      'billing_address' => $this->address(),
      'description' => 'Store Purchase'
    );
  }

  function test_successful_purchase() {
    $this->assertNotNull($response = $this->gateway->purchase($this->amount, $this->credit_card, $this->options));
    $this->assert_success($response);
    $this->assertEquals('Approved', $response->message());
  }

  function test_successful_purchase_with_custom_credit_card_class() {
    $options = array(
      'number' => 4242424242424242,
      'month' => 9,
      'year' => (int)strftime("%Y"),
      'first_name' => 'Longbob',
      'last_name' => 'Longsen',
      'verification_value' => '123',
      'brand' => 'visa'
    );
    $credit_card = $this->credit_card($options["number"], $options);
    $this->assertNotNull($response = $this->gateway->purchase($this->amount, $this->credit_card(), $this->options));
    $this->assert_success($response);
    $this->assertEquals('Approved', $response->message());
  }

  function test_failed_purchase() {
    $this->amount = 1.54; # Expired Card
    $this->assertNotNull($response = $this->gateway->purchase($this->amount, $this->credit_card, $this->options));
    $this->assert_failure($response);

    $this->assertEquals('Expired Card', $response->message());
  }

  function test_authorize_and_capture() {
    $this->assertNotNull($auth = $this->gateway->authorize($this->amount, $this->credit_card, $this->options));
    $this->assert_success($auth);
    $this->assertEquals('Approved', $auth->message());
    $this->assertNotNull($auth->authorization());
    $this->assertNotNull($capture = $this->gateway->capture($this->amount, $auth->authorization()));
    $this->assert_success($capture);
  }

  function test_failed_authorize() {
    $this->amount = 1.51;
    $this->assertNotNull($auth = $this->gateway->authorize($this->amount, $this->credit_card, $this->options));
    $this->assert_failure($auth);
    $this->assertEquals('Insufficient Funds', $auth->message());
  }

  function test_failed_capture() {
    $this->assertNotNull($auth = $this->gateway->authorize($this->amount, $this->credit_card, $this->options));
    $this->assert_success($auth);

    $this->assertNotNull($capture = $this->gateway->capture($this->amount+1, $auth->authorization()));
    $this->assert_failure($capture);
    $this->assertEquals('Preauth was done for smaller amount', $capture->message());
  }

  function test_successful_refund() {
    $this->assertNotNull($response = $this->gateway->purchase($this->amount, $this->credit_card, $this->options));
    $this->assert_success($response);
    $authorization = $response->authorization();

    $this->assertNotNull($response = $this->gateway->refund($this->amount, $authorization));
    $this->assert_success($response);
    $this->assertEquals('Approved', $response->message());
  }

  function test_failed_refund() {
    $this->assertNotNull($response = $this->gateway->purchase($this->amount, $this->credit_card, $this->options));
    $this->assert_success($response);
    $authorization = $response->authorization();

    $this->assertNotNull($response = $this->gateway->refund($this->amount+1, $authorization));
    $this->assert_failure($response);
    $this->assertEquals('Only $100.0 available for refund', $response->message());
  }

  function test_successful_void() {
    $this->assertNotNull($response = $this->gateway->purchase($this->amount, $this->credit_card, $this->options));
    $this->assert_success($response);

    $authorization = $response->authorization();

    $this->assertNotNull($result = $this->gateway->void($authorization));

    $this->assert_success($result);
    $this->assertEquals('Approved', $result->message());
  }

  function test_failed_void() {
    $this->assertNotNull($response = $this->gateway->purchase($this->amount, $this->credit_card, $this->options));
    $this->assert_success($response);
    $authorization = $response->authorization();

    $this->assertNotNull($response = $this->gateway->void($authorization.'1'));
    $this->assert_failure($response);
    $this->assertEquals('Unable to retrieve original FDR txn', $response->message());
  }

  function test_successful_unstore() {
    $this->gateway->store($this->credit_card, array('billing_id' => 'test1234', 'amount' => 15000)); // rescue nil

    $this->assertNotNull($response = $this->gateway->unstore('test1234'));
    $this->assert_success($response);

    $this->assertEquals('Successful', $response->message());
  }

  function test_repeat_unstore() {
    $this->gateway->unstore('test1234'); #rescue nil #Ensure it is already missing;

    $response = $this->gateway->unstore('test1234');

    $this->assert_success($response);
  }

  function test_successful_store() {
    $this->gateway->unstore('test1234'); # rescue nil

    $this->assertNotNull($response = $this->gateway->store($this->credit_card, array('billing_id' => 'test1234', 'amount' => 15000)));
    $this->assert_success($response);

    $this->assertEquals('Successful', $response->message());
  }

  function test_failed_store() {
    $this->gateway->store($this->credit_card, array('billing_id' => 'test1234', 'amount' => 15000)); # rescue nil #Ensure it already exists

    $this->assertNotNull($response = $this->gateway->store($this->credit_card, array('billing_id' => 'test1234', 'amount' => 15000)));
    $this->assert_failure($response);

    $this->assertEquals('Duplicate Client ID Found', $response->message());
  }

  function test_successful_triggered_payment() {
    $store = $this->gateway->store($this->credit_card, array('billing_id' => 'test12346', 'amount' => 15000)); # rescue nil #Ensure it already exists

    $this->assertNotNull($response = $this->gateway->purchase(123, new StoredCreditCard('test12346'), $this->options));
    $this->assert_success($response);
    $this->assertEquals($response->params()['amount'], '12300');

    $this->assertEquals('Approved', $response->message());
  }

  function test_failure_triggered_payment() {
    $this->gateway->unstore('test1234'); # rescue nil #Ensure its no longer there

    $this->assertNotNull($response = $this->gateway->purchase(12300, new StoredCreditCard('test1234'), $this->options));
    $this->assert_failure($response);

    $this->assertEquals('Payment not found', $response->message());
  }

  function test_invalid_login() {
   $gateway = new SecurePayAu(array(
                'login' => 'a',
                'password' => 'a'
              ));
    $response = $gateway->purchase($this->amount, $this->credit_card, $this->options);
    $this->assert_failure($response);
    $this->assertEquals("Invalid merchant ID", $response->message());
  }
}
