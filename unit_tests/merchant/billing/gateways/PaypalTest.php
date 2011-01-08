<?php
/**
 * Description of PaypalTest
 *
 * Usage:
 *   Navigate, from terminal, to folder where this files is located
 *   run phpunit PaypalTest.php
 *
 * @package Active Merchant
 * @author  Andreas Kollaros
 * @license http://www.opensource.org/licenses/mit-license.php
 *
 */
require_once dirname(__FILE__) . '/../../../config.php';

class PaypalTest extends PHPUnit_Framework_TestCase {

  public $gateway;
  public $amount;
  public $options;
  public $creditcard;

  public function setUp() {
    Merchant_Billing_Base::mode('test');

    $this->gateway = new Merchant_Billing_Paypal( array(
      'login' => PAYPAL_PRO_LOGIN,
      'password' => PAYPAL_PRO_PASS,
      'signature' => PAYPAL_PRO_SIG,
      'currency' => 'USD'
      )
    );
    $this->amount = 100;
    $this->creditcard = new Merchant_Billing_CreditCard( array(
        "first_name" => "John",
        "last_name" => "Doe",
        "number" => "4381258770269608",
        "month" => "1",
        "year" => "2015",
        "verification_value" => "000"
      )
    );
    $this->options = array(
      'order_id' => 'REF' . $this->gateway->generate_unique_id(),
      'email' => "buyer@email.com",
      'description' => 'Paypal Pro Test Transaction',
      'billing_address' => array(
        'address1' => '1234 Penny Lane',
        'city' => 'Jonsetown',
        'state' => 'NC',
        'country' => 'US',
        'zip' => '23456'
      ),
      'ip' => '10.0.0.1'
    );
  }

  /**
   * Tests
   */
  public function testSuccessfulPurchase(){
    $response = $this->gateway->purchase($this->amount, $this->creditcard, $this->options);
    $this->assert_success($response);
    $this->assertTrue($response->test());
    $this->assertEquals('Success', $response->message());
  }

  public function testFailedPurchase() {
    $this->creditcard->number = '234234234234';
    $response = $this->gateway->purchase($this->amount, $this->creditcard, $this->options);
    $this->assert_failure($response);
    $this->assertTrue($response->test());
    $this->assertEquals('This transaction cannot be processed. Please enter a valid credit card number and type.', $response->message());
  }

  public function testSuccessfulAuthorization(){
    $response = $this->gateway->authorize($this->amount, $this->creditcard, $this->options);
    $this->assert_success($response);
    $params = $response->params();
    $this->assertEquals('100.00', $params['AMT']);
    $this->assertEquals('USD', $params['CURRENCYCODE']);
  }


  public function testFailedAuthorization() {
    $this->creditcard->number = '234234234234';
    $response = $this->gateway->authorize($this->amount, $this->creditcard, $this->options);
    $this->assert_failure($response);
    $this->assertTrue($response->test());
    $this->assertEquals('This transaction cannot be processed. Please enter a valid credit card number and type.', $response->message());
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
}
?>
