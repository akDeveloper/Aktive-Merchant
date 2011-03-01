<?php
/**
 * Description of ExpireDateTest
 *
 * Usage:
 *   Navigate, from terminal, to folder where this files is located
 *   run phpunit ExpireDateTest.php
 *
 * @package Aktive Merchant
 * @author  Andreas Kollaros
 * @license http://www.opensource.org/licenses/mit-license.php
 *
 */
require_once dirname(__FILE__) . '/../../config.php';

class CreditCardTest extends PHPUnit_Framework_TestCase {
  public $expire_date;
  public $not_expire_date;

  public function setUp() {
    $this->expire_date = new Merchant_Billing_ExpiryDate(5, 2010);
    $this->not_expire_date = new Merchant_Billing_ExpiryDate(12, date('Y',time() + 1 ) );
  }

  public function testSuccessfulExpireDate(){
    $this->assertTrue($this->expire_date->is_expired() );
  }

  public function testFailedExpireDate() {
    $this->assertFalse($this->not_expire_date->is_expired() );
  }

  public function testSuccessfulReturnExpirationTime(){
    $this->assertEquals('1275339599', $this->expire_date->expiration() );
  }
}

?>
