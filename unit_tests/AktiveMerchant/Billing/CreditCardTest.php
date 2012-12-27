<?php

/**
 * CreditCardTest class.
 *
 * @package Aktive-Merchant
 * @author  Andreas Kollaros
 * @license http://www.opensource.org/licenses/mit-license.php MIT License
 *
 */
require_once '../autoload.php';

class CreditCardTest extends PHPUnit_Framework_TestCase
{

    public $creditcard;

    public function setUp()
    {
        $this->creditcard = new \AktiveMerchant\Billing\CreditCard(array(
                "first_name" => "John",
                "last_name" => "Doe",
                "number" => "4381258770269608",
                "month" => "1",
                "year" => "2015",
                "verification_value" => "000"
                )
        );
    }

    public function testSuccessfulValidateCreditcard()
    {
        $this->assertTrue($this->creditcard->isValid());
    }

    public function testSuccessfulGetDisplayNumber()
    {
        $this->assertEquals(
            'XXXX-XXXX-XXXX-9608',
            $this->creditcard->displayNumber()
        );
    }

    public function testSuccessfulGetLastDigits()
    {
        $this->assertEquals('9608', $this->creditcard->lastDigits());
    }

    public function testSuccessfulGetName()
    {
        $this->assertEquals('John Doe', $this->creditcard->name());
    }

    public function testSuccessfulExpireDate()
    {
        $expire_date = $this->creditcard->expireDate();
        $this->assertInstanceOf(
            '\AktiveMerchant\Billing\ExpiryDate',
            $expire_date
        );
        $this->assertFalse($expire_date->isExpired());
    }

    public function testFailedExpireDate()
    {
        $this->creditcard->year = 2000;
        $expire_date = $this->creditcard->expireDate();
        $this->assertInstanceOf(
            '\AktiveMerchant\Billing\ExpiryDate',
            $expire_date
        );
        $this->assertTrue($expire_date->isExpired());
    }

}

?>
