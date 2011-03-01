<?php
/**
 * Description of CreditCardTest
 *
 * Usage:
 *   Navigate, from terminal, to folder where this files is located
 *   run phpunit CreditCardTest.php
 *
 * @package Aktive Merchant
 * @author  Andreas Kollaros
 * @license http://www.opensource.org/licenses/mit-license.php
 *
 */
require_once dirname(__FILE__) . '/../../config.php';

class CreditCardTest extends PHPUnit_Framework_TestCase {
  public $creditcard;

  public function setUp() {
    $this->creditcard = new Merchant_Billing_CreditCard( array(
        "first_name" => "John",
        "last_name" => "Doe",
        "number" => "4381258770269608",
        "month" => "1",
        "year" => "2015",
        "verification_value" => "000"
      )
    );
  }

  public function testSuccessfulValidateCreditcard(){
    $this->assertTrue($this->creditcard->is_valid());
  }

  public function testSuccessfulGetDisplayNumber(){
    $this->assertEquals('XXXX-XXXX-XXXX-9608',$this->creditcard->display_number());
  }

  public function testSuccessfulGetLastDigits(){
    $this->assertEquals('9608',$this->creditcard->last_digits());
  }

  public function testSuccessfulGetName(){
    $this->assertEquals('John Doe',$this->creditcard->name());
  }

  public function testSuccessfulExpireDate() {
    $expire_date = $this->creditcard->expire_date();
    $this->assertInstanceOf('Merchant_Billing_ExpiryDate', $expire_date);
    $this->assertFalse($expire_date->is_expired());
  }

  public function testFailedExpireDate() {
    $this->creditcard->year = 2000;
    $expire_date = $this->creditcard->expire_date();
    $this->assertInstanceOf('Merchant_Billing_ExpiryDate', $expire_date);
    $this->assertTrue($expire_date->is_expired());
  }

}

?>
